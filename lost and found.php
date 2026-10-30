<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'lostfound_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('UPLOAD_DIR', 'uploads/');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

class Database {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function setup() {
        try {
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
            $this->pdo->exec("USE " . DB_NAME);
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    category VARCHAR(100),
                    status ENUM('lost', 'found') NOT NULL,
                    location VARCHAR(255),
                    image_path VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (PDOException $e) {
            die("Database setup failed: " . $e->getMessage());
        }
    }
}

class Item {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->setup();
    }

    public function create($title, $description, $category, $status, $location, $imagePath) {
        $stmt = $this->db->getConnection()->prepare("INSERT INTO items (title, description, category, status, location, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $category, $status, $location, $imagePath]);
    }

    public function readAll() {
        $stmt = $this->db->getConnection()->query("SELECT * FROM items ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function read($id) {
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $title, $description, $category, $status, $location, $imagePath = null) {
        if ($imagePath) {
            $stmt = $this->db->getConnection()->prepare("UPDATE items SET title = ?, description = ?, category = ?, status = ?, location = ?, image_path = ? WHERE id = ?");
            return $stmt->execute([$title, $description, $category, $status, $location, $imagePath, $id]);
        } else {
            $stmt = $this->db->getConnection()->prepare("UPDATE items SET title = ?, description = ?, category = ?, status = ?, location = ? WHERE id = ?");
            return $stmt->execute([$title, $description, $category, $status, $location, $id]);
        }
    }

    public function delete($id) {
        $stmt = $this->db->getConnection()->prepare("DELETE FROM items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function uploadImage($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes) || $file['size'] > 2 * 1024 * 1024) return null; 
        $filename = uniqid() . '_' . basename($file['name']);
        $path = UPLOAD_DIR . $filename;
        if (move_uploaded_file($file['tmp_name'], $path)) return $path;
        return null;
    }
}

$item = new Item();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create'])) {
        $imagePath = $item->uploadImage($_FILES['image'] ?? null);
        $item->create($_POST['title'], $_POST['description'], $_POST['category'], $_POST['status'], $_POST['location'], $imagePath);
    } elseif (isset($_POST['update'])) {
        $imagePath = $item->uploadImage($_FILES['image'] ?? null);
        $item->update($_POST['id'], $_POST['title'], $_POST['description'], $_POST['category'], $_POST['status'], $_POST['location'], $imagePath);
    } elseif (isset($_POST['delete'])) {
        $item->delete($_POST['id']);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);  
    exit;
}

$items = $item->readAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lost and Found</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { margin-bottom: 20px; }
        input, textarea, select { margin: 5px; padding: 5px; }
        img { max-width: 200px; }
        .item { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Lost and Found System</h1>

    <!-- Create Form -->
    <h2>Add New Item</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Title" required><br>
        <textarea name="description" placeholder="Description"></textarea><br>
        <input type="text" name="category" placeholder="Category (e.g., Electronics)"><br>
        <select name="status" required>
            <option value="lost">Lost</option>
            <option value="found">Found</option>
        </select><br>
        <input type="text" name="location" placeholder="Location" required><br>
        <input type="file" name="image" accept="image/*"><br>
        <button type="submit" name="create">Add Item</button>
    </form>

    <!-- List Items -->
    <h2>Items</h2>
    <?php if (empty($items)): ?>
        <p>No items found.</p>
    <?php else: ?>
        <?php foreach ($items as $i): ?>
            <div class="item">
                <h3><?php echo htmlspecialchars($i['title']); ?> (<?php echo $i['status']; ?>)</h3>
                <p><?php echo htmlspecialchars($i['description']); ?></p>
                <p>Category: <?php echo htmlspecialchars($i['category']); ?></p>
                <p>Location: <?php echo htmlspecialchars($i['location']); ?></p>
                <?php if ($i['image_path']): ?>
                    <img src="<?php echo htmlspecialchars($i['image_path']); ?>" alt="Item Image"><br>
                <?php endif; ?>
                <p>Created: <?php echo $i['created_at']; ?></p>

                <!-- Update Form -->
                <form method="POST" enctype="multipart/form-data" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                    <input type="text" name="title" value="<?php echo htmlspecialchars($i['title']); ?>" required><br>
                    <textarea name="description"><?php echo htmlspecialchars($i['description']); ?></textarea><br>
                    <input type="text" name="category" value="<?php echo htmlspecialchars($i['category']); ?>"><br>
                    <select name="status" required>
                        <option value="lost" <?php if ($i['status'] == 'lost') echo 'selected'; ?>>Lost</option>
                        <option value="found" <?php if ($i['status'] == 'found') echo 'selected'; ?>>Found</option>
                    </select><br>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($i['location']); ?>" required><br>
                    <input type="file" name="image" accept="image/*"><br>
                    <button type="submit" name="update">Update</button>
                </form>

                <!-- Delete Form -->
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo $i['id']; ?>">
                    <button type="submit" name="delete" onclick="return confirm('Delete this item?')">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
