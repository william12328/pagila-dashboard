<div class="table-responsive">
    <table class="table commerce-table table-hover">
        <thead>
            <tr>
                <th>Film</th>
                <th>Kategori</th>
                <th>Rating</th>
                <th>Rental Rate</th>
                <th>Rented</th>
                <th>Revenue</th>
                <th>Stok</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productRows as $product): ?>
                <?php $stockClass = (int)$product['stock_qty'] <= (int)$product['reorder_level'] ? 'status-risk' : 'status-good'; ?>
                <tr>
                    <td><strong><?= h($product['product_name']); ?></strong><br><span class="text-muted">Film #<?= h($product['product_id']); ?></span></td>
                    <td><?= h($product['category']); ?></td>
                    <td><?= h($product['format_type']); ?></td>
                    <td><?= dollar($product['price']); ?></td>
                    <td><?= number_format($product['units_sold']); ?></td>
                    <td class="fw-bold"><?= dollar($product['revenue']); ?></td>
                    <td><span class="status-pill <?= h($stockClass); ?>"><?= number_format($product['stock_qty']); ?> unit</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
