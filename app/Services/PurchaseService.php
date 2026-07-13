<?php
/**
 * Purchase Form
 * - invoice_no auto 1,2,3… per FY (type PUR)
 * - sequence_no SHARED with CRV / CPV / JV
 * - Line: qty×rate → disc1% → amount → disc2% → net
 * - Footer: lines total + bill expense = final net
 * - Stock increases on save; last purchase_rate updated on item
 * - Ledger:
 *     Credit → Party Cr (payable)
 *     Cash   → Cash/Bank Cr
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

class PurchaseService
{
    public static function previewNext(string $date): array
    {
        $fy = FinancialYearService::resolveForDate($date);
        $db = Database::getInstance();

        $nextNo = (int)$db->fetchColumn(
            'SELECT COALESCE(MAX(voucher_no),0)+1 FROM voucher_sequence
             WHERE financial_year_id = :fy AND voucher_type = :t',
            ['fy' => $fy['id'], 't' => 'PUR']
        );
        $nextSeq = (int)$db->fetchColumn(
            'SELECT COALESCE(MAX(sequence_no),0)+1 FROM voucher_sequence
             WHERE financial_year_id = :fy',
            ['fy' => $fy['id']]
        );

        return [
            'invoice_no'  => $nextNo,
            'voucher_no'  => $nextNo,
            'voucher_ref' => (string)$nextNo,
            'sequence_no' => $nextSeq,
            'fy'          => $fy,
        ];
    }

    /**
     * @param array $data
     */
    public static function save(array $data): array
    {
        $date = trim((string)($data['purchase_date'] ?? ''));
        $billNo = trim((string)($data['bill_no'] ?? ''));
        $payMode = ($data['pay_mode'] ?? 'Credit') === 'Cash' ? 'Cash' : 'Credit';
        $partyId = (int)($data['party_id'] ?? 0);
        $company = trim((string)($data['company_name'] ?? ''));
        $cashId = (int)($data['cash_account_id'] ?? 0);
        $billExp = round((float)($data['bill_expense'] ?? 0), 2);
        $headerNarr = trim((string)($data['narration'] ?? ''));
        $lines = $data['lines'] ?? [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Invalid purchase date.');
        }
        if ($partyId <= 0) {
            throw new RuntimeException('Select party / supplier.');
        }
        if ($billExp < 0) {
            throw new RuntimeException('Bill expense cannot be negative.');
        }
        if (!is_array($lines) || count($lines) < 1) {
            throw new RuntimeException('Add at least one item line.');
        }

        $db = Database::getInstance();
        $userId = Auth::id();

        $party = $db->fetch(
            'SELECT id, title FROM subsidiary_heads WHERE id = :id AND is_active = 1',
            ['id' => $partyId]
        );
        if (!$party) {
            throw new RuntimeException('Invalid party account.');
        }

        if ($payMode === 'Cash') {
            if ($cashId <= 0) {
                throw new RuntimeException('Select cash / bank account for cash purchase.');
            }
            $cash = $db->fetch(
                "SELECT id FROM subsidiary_heads
                 WHERE id = :id AND is_active = 1 AND account_type IN ('cash','bank')",
                ['id' => $cashId]
            );
            if (!$cash) {
                throw new RuntimeException('Invalid cash / bank account.');
            }
        }

        $clean = [];
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $itemId = (int)($line['item_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 3);
            $rate = round((float)($line['rate'] ?? 0), 2);
            $d1 = round((float)($line['disc1_pct'] ?? 0), 2);
            $d2 = round((float)($line['disc2_pct'] ?? 0), 2);
            $batch = trim((string)($line['batch_no'] ?? ''));

            if ($itemId <= 0 || $qty <= 0 || $rate < 0) {
                continue;
            }
            if ($d1 < 0 || $d1 > 100 || $d2 < 0 || $d2 > 100) {
                throw new RuntimeException('Discount % must be between 0 and 100.');
            }

            $item = $db->fetch(
                'SELECT id, item_code, item_name, packing FROM items WHERE id = :id AND is_active = 1',
                ['id' => $itemId]
            );
            if (!$item) {
                throw new RuntimeException('Invalid item on a line.');
            }

            // qty × rate
            $gross = round($qty * $rate, 2);
            // first discount %
            $disc1Amt = round($gross * $d1 / 100, 2);
            $afterD1 = round($gross - $disc1Amt, 2);
            // second discount % on amount after first discount
            $disc2Amt = round($afterD1 * $d2 / 100, 2);
            $net = round($afterD1 - $disc2Amt, 2);

            $clean[] = [
                'item_id'         => $itemId,
                'item_code'       => $item['item_code'],
                'item_title'      => $item['item_name'],
                'packing'         => $item['packing'],
                'batch_no'        => $batch !== '' ? mb_substr($batch, 0, 50) : null,
                'qty'             => $qty,
                'rate'            => $rate,
                'gross_amount'    => $gross,
                'disc1_pct'       => $d1,
                'disc1_amt'       => $disc1Amt,
                'amount_after_d1' => $afterD1,
                'disc2_pct'       => $d2,
                'disc2_amt'       => $disc2Amt,
                'net_amount'      => $net,
                'line_no'         => count($clean) + 1,
            ];
            $subtotal += $net;
        }

        if (!$clean) {
            throw new RuntimeException('Enter valid item lines with qty and rate.');
        }

        $subtotal = round($subtotal, 2);
        $netAmount = round($subtotal + $billExp, 2);
        if ($netAmount <= 0) {
            throw new RuntimeException('Net amount must be greater than zero.');
        }

        $fy = FinancialYearService::resolveForDate($date);
        $narr = $headerNarr !== ''
            ? mb_substr($headerNarr, 0, 500)
            : ('Purchase' . ($billNo !== '' ? ' bill ' . $billNo : '') . ' — ' . $party['title']);

        try {
            $db->beginTransaction();

            // Shared sequence lock (CRV/CPV/JV/PUR)
            $db->query(
                'SELECT id FROM voucher_sequence WHERE financial_year_id = :fy FOR UPDATE',
                ['fy' => $fy['id']]
            );

            $invoiceNo = (int)$db->fetchColumn(
                'SELECT COALESCE(MAX(voucher_no),0)+1 FROM voucher_sequence
                 WHERE financial_year_id = :fy AND voucher_type = :t',
                ['fy' => $fy['id'], 't' => 'PUR']
            );
            $sequenceNo = (int)$db->fetchColumn(
                'SELECT COALESCE(MAX(sequence_no),0)+1 FROM voucher_sequence
                 WHERE financial_year_id = :fy',
                ['fy' => $fy['id']]
            );
            $voucherRef = (string)$invoiceNo;

            $db->query(
                'INSERT INTO purchases
                   (financial_year_id, invoice_no, voucher_ref, purchase_date, bill_no, pay_mode,
                    party_id, company_name, cash_account_id, subtotal, bill_expense, net_amount,
                    narration, created_by)
                 VALUES
                   (:fy, :inv, :ref, :dt, :bill, :mode, :party, :co, :cash, :sub, :exp, :net, :narr, :uid)',
                [
                    'fy'    => $fy['id'],
                    'inv'   => $invoiceNo,
                    'ref'   => $voucherRef,
                    'dt'    => $date,
                    'bill'  => $billNo !== '' ? mb_substr($billNo, 0, 50) : null,
                    'mode'  => $payMode,
                    'party' => $partyId,
                    'co'    => $company !== '' ? mb_substr($company, 0, 150) : null,
                    'cash'  => $payMode === 'Cash' ? $cashId : null,
                    'sub'   => $subtotal,
                    'exp'   => $billExp,
                    'net'   => $netAmount,
                    'narr'  => $narr,
                    'uid'   => $userId,
                ]
            );
            $purchaseId = (int)$db->lastInsertId();

            $ins = $db->pdo()->prepare(
                'INSERT INTO purchase_details
                   (purchase_id, line_no, item_id, item_code, item_title, packing, batch_no,
                    qty, rate, gross_amount, disc1_pct, disc1_amt, amount_after_d1,
                    disc2_pct, disc2_amt, net_amount)
                 VALUES
                   (:pid, :ln, :iid, :code, :title, :pack, :batch,
                    :qty, :rate, :gross, :d1p, :d1a, :a1, :d2p, :d2a, :net)'
            );
            $stockUpd = $db->pdo()->prepare(
                'UPDATE items SET stock = stock + :qty, purchase_rate = :rate WHERE id = :id'
            );

            foreach ($clean as $line) {
                $ins->execute([
                    'pid'   => $purchaseId,
                    'ln'    => $line['line_no'],
                    'iid'   => $line['item_id'],
                    'code'  => $line['item_code'],
                    'title' => $line['item_title'],
                    'pack'  => $line['packing'],
                    'batch' => $line['batch_no'],
                    'qty'   => $line['qty'],
                    'rate'  => $line['rate'],
                    'gross' => $line['gross_amount'],
                    'd1p'   => $line['disc1_pct'],
                    'd1a'   => $line['disc1_amt'],
                    'a1'    => $line['amount_after_d1'],
                    'd2p'   => $line['disc2_pct'],
                    'd2a'   => $line['disc2_amt'],
                    'net'   => $line['net_amount'],
                ]);
                $stockUpd->execute([
                    'qty'  => $line['qty'],
                    'rate' => $line['rate'],
                    'id'   => $line['item_id'],
                ]);
            }

            $db->query(
                'INSERT INTO voucher_sequence
                   (financial_year_id, sequence_no, voucher_type, voucher_id, voucher_no, voucher_ref, voucher_date)
                 VALUES
                   (:fy, :seq, :vt, :vid, :vno, :ref, :dt)',
                [
                    'fy'  => $fy['id'],
                    'seq' => $sequenceNo,
                    'vt'  => 'PUR',
                    'vid' => $purchaseId,
                    'vno' => $invoiceNo,
                    'ref' => $voucherRef,
                    'dt'  => $date,
                ]
            );

            // Ledger: credit purchase → Party Cr; cash purchase → Cash Cr
            // (Stock qty is the inventory record; full Purchase Dr GL can be extended later)
            if ($payMode === 'Cash') {
                $ledgerLines = [[
                    'subsidiary_id' => $cashId,
                    'narration'     => $narr,
                    'debit'         => 0,
                    'credit'        => $netAmount,
                ]];
            } else {
                $ledgerLines = [[
                    'subsidiary_id' => $partyId,
                    'narration'     => $narr,
                    'debit'         => 0,
                    'credit'        => $netAmount,
                ]];
            }

            LedgerService::post(
                (int)$fy['id'],
                $sequenceNo,
                'PUR',
                $purchaseId,
                $voucherRef,
                $date,
                $ledgerLines,
                $userId
            );

            $db->commit();

            Audit::log(
                $userId,
                'create',
                'purchase',
                $voucherRef,
                "Purchase #{$invoiceNo} net {$netAmount} ({$payMode})"
            );

            return [
                'purchase_id'  => $purchaseId,
                'invoice_no'   => $invoiceNo,
                'voucher_no'   => $invoiceNo,
                'voucher_ref'  => $voucherRef,
                'sequence_no'  => $sequenceNo,
                'subtotal'     => $subtotal,
                'bill_expense' => $billExp,
                'net_amount'   => $netAmount,
                'fy_code'      => $fy['code'],
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
