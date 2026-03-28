<h2>Lập phiếu nhập hàng</h2>
<div style="margin-bottom: 20px;">
    <input type="text" id="searchProduct" placeholder="Tìm tên sản phẩm...">
    <button type="button" onclick="addProductRow()">Thêm vào danh sách</button>
</div>

<form action="save_import.php" method="POST">
    <table id="importTable" border="1" width="100%">
        <thead>
            <tr>
                <th>Sản phẩm</th>
                <th>Số lượng</th>
                <th>Giá nhập</th>
                <th>Xóa</th>
            </tr>
        </thead>
        <tbody>
            </tbody>
    </table>
    <br>
    <button type="submit" name="status" value="draft">Lưu bản nháp</button>
    <button type="submit" name="status" value="completed" onclick="return confirm('Sau khi hoàn thành không thể sửa. Xác nhận?')">Hoàn thành & Cập nhật kho</button>
</form>

<script>
function addProductRow() {
    const table = document.getElementById('importTable').getElementsByTagName('tbody')[0];
    const row = table.insertRow();
    row.innerHTML = `
        <td>
            <select name="product_id[]" required>
                <?php
                // Load danh sách sản phẩm để chọn
                $stmt = $pdo->query("SELECT id, name FROM products WHERE is_deleted = 0");
                while($p = $stmt->fetch()) {
                    echo "<option value='{$p['id']}'>{$p['name']}</option>";
                }
                ?>
            </select>
        </td>
        <td><input type="number" name="quantity[]" min="1" required></td>
        <td><input type="number" name="price[]" min="0" required></td>
        <td><button type="button" onclick="this.parentElement.parentElement.remove()">Xóa</button></td>
    `;
}
</script>
