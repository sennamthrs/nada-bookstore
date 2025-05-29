<?php
// Hubungkan ke database
$koneksi = new mysqli("localhost", "username", "password", "database");

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Ambil data produk
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = $koneksi->query("SELECT * FROM produk WHERE id = '$id'");
    $produk = $result->fetch_assoc();
}

// Proses update data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_produk = $_POST['nama_produk'];
    $kategori = $_POST['kategori'];
    $harga = $_POST['harga'];

    $koneksi->query("UPDATE produk SET nama_produk='$nama_produk', kategori='$kategori', harga='$harga' WHERE id='$id'");
    header("Location: admin.php");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk</title>
</head>
<body>

<h1>Edit Produk</h1>

<form method="post">
    <label>Nama Produk:</label>
    <input type="text" name="nama_produk" value="<?php echo $produk['nama_produk']; ?>" required>
    
    <label>Kategori:</label>
    <input type="text" name="kategori" value="<?php echo $produk['kategori']; ?>" required>
    
    <label>Harga:</label>
    <input type="number" name="harga" value="<?php echo $produk['harga']; ?>" required>
    
    <button type="submit">Update</button>
    <a href="admin.php">Batal</a>
</form>

</body>
</html>

<?php
$koneksi->close();
?>
