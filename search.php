
$name = $_GET['name'] ?? '';
$cat_id = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? 0;
$max_price = $_GET['max_price'] ?? 999999999;


$sql = "SELECT *, (import_price * (1 + profit_rate)) AS selling_price 
        FROM products WHERE 1=1";

if ($name != '') {
    $sql .= " AND name LIKE '%$name%'";
}
if ($cat_id != '') {
    $sql .= " AND category_id = $cat_id";
}


$sql .= " HAVING selling_price BETWEEN $min_price AND $max_price";


$limit = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$sql .= " LIMIT $limit OFFSET $offset";
