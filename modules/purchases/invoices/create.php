        if ($status !== 'paid' && ($grand - $paid_amount) > 0) {
            $deferred = $grand - $paid_amount;
            // Get current balance for supplier
            $lastBalance = $db->query("SELECT balance_after FROM supplier_transactions WHERE supplier_id = $supplier_id ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
            $newBalance = floatval($lastBalance) + $deferred;
            $db->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, reference_type, reference_id, reference_number, debit, credit, balance_after, notes, created_by, created_at) VALUES (?, 'purchase', 'purchase_invoice', ?, ?, ?, 0, ?, ?, ?, NOW())")
               ->execute([$supplier_id, $inv_id, $inv_number, $deferred, $newBalance, 'مبلغ مؤجل من فاتورة ' . $inv_number, $_SESSION['user_id']]);
        }
        logActivity('purchase_invoice_create', 'purchase_invoices', $inv_id); $db->commit();