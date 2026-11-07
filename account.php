
<?php
    
session_start();

require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";


if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";
$name = "";
$email = "";

// Load the customer's current details
$stmt = $conn->prepare(
    "SELECT name, email, password FROM users WHERE id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

$name = $user["name"];
$email = $user["email"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	if (!csrf_validate()) {
    http_response_code(403);
    exit("Invalid security token. Please refresh the page and try again.");
	}
	
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || strlen($name) > 100) {
        $message = "Please enter a name of no more than 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $message = "Please enter a valid email address.";
    } else {
        // Check whether another customer uses this email
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ? AND id != ?"
        );
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $email_exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($email_exists) {
            $message = "That email address is already in use.";
        } else {
            $changing_password = (
                $current_password !== "" ||
                $new_password !== "" ||
                $confirm_password !== ""
            );

            
			if ($changing_password) {
				if (!password_verify($current_password, $user["password"])) {
					$message = "Your current password is incorrect.";

				} elseif (
					strlen($new_password) < 12 ||
					!preg_match('/[A-Z]/', $new_password) ||
					!preg_match('/[a-z]/', $new_password) ||
					!preg_match('/[0-9]/', $new_password) ||
					!preg_match('/[^a-zA-Z0-9]/', $new_password)
				) {
					$message = "New password rejected. It must contain at least 12 characters, an uppercase letter, a lowercase letter, a number, and a special character.";

				} elseif ($new_password !== $confirm_password) {
					$message = "The new passwords do not match.";

				} elseif (password_verify($new_password, $user["password"])) {
					$message = "Choose a password different from your current password.";
				}
}
			}}
            if ($message === "") {
                if ($changing_password) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare(
                        "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?"
                    );
                    $stmt->bind_param(
                        "sssi",
                        $name,
                        $email,
                        $password_hash,
                        $user_id
                    );
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE users SET name = ?, email = ? WHERE id = ?"
                    );
                    $stmt->bind_param("ssi", $name, $email, $user_id);
                }

                if ($stmt->execute()) {
                    $_SESSION["user_name"] = $name;
                    $message = "Your account details have been updated.";
                    $message_type = "success";

                    // Refresh the stored account details
                    $stmt->close();

                    $stmt = $conn->prepare(
                        "SELECT name, email, password FROM users WHERE id = ?"
                    );
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } else {
                    $message = "Unable to update your details. Please try again.";
                    $stmt->close();
                }
            }
        }
    


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Workspace Wizards</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/account.css">
</head>
<body>

<header>
    <h1>Workspace Wizards</h1>
         <nav>
        <a href="home.php">Home</a>
        <a href="products.php">Products</a>

        <?php if (isset($_SESSION["user_id"])): ?>

            <?php if (
                isset($_SESSION["user_role"]) &&
                $_SESSION["user_role"] === "admin"
            ): ?>
                <a href="admin/index.php">Admin Dashboard</a>
                <a href="account.php">My Account</a>

            <?php else: ?>
                <a href="orders.php">My Orders</a>
                <a href="cart.php">Cart</a>
                <a href="account.php">My Account</a>

            <?php endif; ?>

            <form method="POST" action="logout.php">
                <?php echo csrf_field(); ?>
                <button type="submit">Logout</button>
            </form>

        <?php else: ?>

            <a href="login.php">Login</a>
            <a href="register.php">Register</a>

        <?php endif; ?>
    </nav>
    
</header>

<main>
    <h2>My Account</h2>


	<?php if ($message !== ""): ?>
		<div>
			role="<?php echo $message_type === "success" ? "status" : "alert"; ?>"
			style="padding: 12px; margin: 15px 0;
				   border: 1px solid <?php echo $message_type === "success" ? "#15803d" : "#b91c1c"; ?>;
				   background: <?php echo $message_type === "success" ? "#f0fdf4" : "#fef2f2"; ?>;
				   color: <?php echo $message_type === "success" ? "#166534" : "#991b1b"; ?>;
				   border-radius: 5px;
             
			<?php echo htmlspecialchars($message); ?>
		</div>
	<?php endif; ?>

    <form method="POST" action="account.php">
	<?php echo csrf_field(); ?>
        <h3>Personal Details</h3>

        <label for="name">Full Name</label><br>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            value="<?php echo htmlspecialchars($name); ?>"
            required
        ><br><br>

        <label for="email">Email Address</label><br>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            value="<?php echo htmlspecialchars($email); ?>"
            required
        ><br><br>

        <h3>Change Password (optional)</h3>
        <p>Leave all three password fields blank if you do not want to change your password.</p>

        <label for="current_password">Current Password</label><br>
        <input
            type="password"
            id="current_password"
            name="current_password"
            autocomplete="current-password"
        ><br><br>

        <label for="new_password">New Password</label><br>
        <input
            type="password"
            id="new_password"
            name="new_password"
			minlength="12"
			pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}"
			title="At least 12 characters, including uppercase, lowercase, a number, and a special character."
            autocomplete="new-password"
        ><br><br>

        <label for="confirm_password">Confirm New Password</label><br>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="12"
            autocomplete="new-password"
        ><br><br>

        <button type="submit">Save Changes</button>
    </form>
</main>

</body>
</html>