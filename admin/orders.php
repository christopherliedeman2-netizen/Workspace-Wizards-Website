<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";

// Only administrators may access this page.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$error = "";
$message = "";
$search = trim($_GET["search"] ?? "");

// Update fulfilment status.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {
    if (!csrf_validate()) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page and try again.");
    }

    $orderId = filter_var(
        $_POST["order_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    $status = $_POST["status"] ?? "";

    $allowedStatuses = [
        "Pending",
        "Processing",
        "Shipped",
        "Completed",
        "Cancelled"
    ];

    if (
        $orderId === false ||
        $orderId === null ||
        $orderId < 1 ||
        !in_array($status, $allowedStatuses, true)
    ) {
        $error = "Invalid order or status selected.";
    } else {

        // Check the current order status.
       $check = $conn->prepare(
			"SELECT status, payment_status
			 FROM orders
			 WHERE id = ?"
		);

        $check->bind_param("i", $orderId);
        $check->execute();

        $checkResult = $check->get_result();
        $existingOrder = $checkResult->fetch_assoc();

        $check->close();

        if (!$existingOrder) {

            $error = "The selected order could not be found.";

        } elseif (
			$existingOrder["status"] === "Cancelled" ||
			$existingOrder["status"] === "Completed"
		) {

			$error = "This order can no longer be modified.";

		} elseif (
			in_array($status, ["Shipped", "Completed"], true) &&
			$existingOrder["payment_status"] !== "Paid"
		) {

			$error = "An order cannot be marked as completed until payment has been confirmed.";

		} else {


            // Update the order only if it is still modifiable.
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET status = ?
                 WHERE id = ?
                   AND status NOT IN ('Cancelled', 'Completed')"
            );

            $stmt->bind_param("si", $status, $orderId);

            if ($stmt->execute()) {
                $stmt->close();

                header("Location: orders.php?updated=1");
                exit;
            }

            $error = "The order status could not be updated.";

            $stmt->close();
        }
    }
}

if (isset($_GET["updated"])) {
    $message = "Order status updated successfully.";
}

/* --------------------------------------------------
   Order filters
-------------------------------------------------- */

$search = trim($_GET["search"] ?? "");

$statusFilter = $_GET["status"] ?? "";
$paymentFilter = $_GET["payment_status"] ?? "";

$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

/* Validate fulfilment status filter. */

$allowedStatuses = [
    "Pending",
    "Processing",
    "Shipped",
    "Completed",
    "Cancelled"
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "";
}

/* Validate payment status filter. */

$allowedPaymentStatuses = [
    "Pending",
    "Paid",
    "Cancelled",
    "Failed"
];

if (!in_array($paymentFilter, $allowedPaymentStatuses, true)) {
    $paymentFilter = "";
}

/* Validate date filters. */

foreach (["fromDate", "toDate"] as $dateVariable) {
    if ($$dateVariable !== "") {
        $dateObject = DateTime::createFromFormat(
            "!Y-m-d",
            $$dateVariable
        );

        if (
            !$dateObject ||
            $dateObject->format("Y-m-d") !== $$dateVariable
        ) {
            $$dateVariable = "";
        }
    }
}

if (
    $fromDate !== "" &&
    $toDate !== "" &&
    $fromDate > $toDate
) {
    $error = "The start date cannot be later than the end date.";
    $fromDate = "";
    $toDate = "";
}

/* --------------------------------------------------
   Retrieve filtered orders
-------------------------------------------------- */

$searchTerm = "%" . $search . "%";

$stmt = $conn->prepare(
    "SELECT
        o.id,
        o.user_id,
        o.total,
        o.status,
        o.payment_status,
        o.payment_method,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email
     FROM orders o
     INNER JOIN users u ON o.user_id = u.id
     WHERE
        (
            ? = ''
            OR CAST(o.id AS CHAR) LIKE ?
            OR u.name LIKE ?
            OR u.email LIKE ?
        )
        AND (? = '' OR o.status = ?)
        AND (? = '' OR o.payment_status = ?)
        AND (? = '' OR DATE(o.created_at) >= ?)
        AND (? = '' OR DATE(o.created_at) <= ?)
     ORDER BY o.created_at DESC"
);

$stmt->bind_param(
    "ssssssssssss",
    $search,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $statusFilter,
    $statusFilter,
    $paymentFilter,
    $paymentFilter,
    $fromDate,
    $fromDate,
    $toDate,
    $toDate
);

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Orders | WorkspaceWizards</title>

    <link rel="stylesheet" href="../css/style.css">
	<link rel="stylesheet" href="../css/admin-orders.css">
    
</head>

<body>

<header>

    <h1>Manage Orders</h1>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="products.php">Manage Products</a>
		<a href="../products.php">View Store</a>
		<form method="POST" action="../logout.php">
			<?php echo csrf_field(); ?>
			<button type="submit">Logout</button>
		</form>
    </nav>

</header>

<main>

    <h2>Customer Orders</h2>

    <form method="GET" action="orders.php" class="order-filter-form">

    <label for="search">Search orders</label>

    <input
        type="search"
        id="search"
        name="search"
        placeholder="Order number, customer name or email"
        value="<?php echo htmlspecialchars(
            $search,
            ENT_QUOTES,
            "UTF-8"
        ); ?>"
    >

    <div class="order-filter-grid">

        <div>
            <label for="status">Fulfilment status</label>

            <select id="status" name="status">
                <option value="">All statuses</option>

                <?php foreach (
                    ["Pending", "Processing", "Shipped", "Completed", "Cancelled"]
                    as $option
                ): ?>
                    <option
                        value="<?php echo $option; ?>"
                        <?php echo $statusFilter === $option ? "selected" : ""; ?>
                    >
                        <?php echo $option; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="payment_status">Payment status</label>

            <select id="payment_status" name="payment_status">
                <option value="">All payments</option>

                <?php foreach (
                    ["Pending", "Paid", "Cancelled", "Failed"]
                    as $option
                ): ?>
                    <option
                        value="<?php echo $option; ?>"
                        <?php echo $paymentFilter === $option ? "selected" : ""; ?>
                    >
                        <?php echo $option; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="from_date">From date</label>

            <input
                type="date"
                id="from_date"
                name="from_date"
                value="<?php echo htmlspecialchars(
                    $fromDate,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
            >
        </div>

        <div>
            <label for="to_date">To date</label>

            <input
                type="date"
                id="to_date"
                name="to_date"
                value="<?php echo htmlspecialchars(
                    $toDate,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
            >
        </div>

    </div>

    <button type="submit">Apply Filters</button>

    <a href="orders.php" class="clear-filters-link">
        Clear Filters
    </a>

</form>

    <br>

    <?php if ($message !== ""): ?>

        <p style="color: green;">

            <?php echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </p>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <p style="color: red;">

            <?php echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

        </p>

    <?php endif; ?>


    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($order = $result->fetch_assoc()): ?>

            <section class="product-card admin-order-card status-<?php
                        echo strtolower(htmlspecialchars(
                            $order["status"],
                            ENT_QUOTES,
                            "UTF-8"
                        ));
                    ?>"
                    data-order-id="<?php echo (int)$order["id"]; ?>"
                    data-customer="<?php echo htmlspecialchars(
                        $order["customer_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    data-email="<?php echo htmlspecialchars(
                        $order["customer_email"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    data-status="<?php echo htmlspecialchars(
                        $order["status"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    data-payment-status="<?php echo htmlspecialchars(
                        $order["payment_status"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    style="margin-bottom: 25px;"
                >

                <h3>
                    Order #<?php echo (int)$order["id"]; ?>
                </h3>


                <p>

                    <strong>Customer:</strong>

                    <?php echo htmlspecialchars(
                        $order["customer_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


                <p>

                    <strong>Email:</strong>

                    <?php echo htmlspecialchars(
                        $order["customer_email"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


                <p>

                    <strong>Order date:</strong>

                    <?php echo htmlspecialchars(
                        $order["created_at"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


                <p>

                    <strong>Total:</strong>

                    R<?php echo number_format(
                        (float)$order["total"],
                        2
                    ); ?>

                </p>


                
                <p>
                    <strong>Payment status:</strong>

                    <?php
                    $payment_status = $order["payment_status"];

                    $payment_badge_class = match ($payment_status) {
                        "Paid" => "paid",
                        "Pending" => "payment-pending",
                        default => "payment-pending"
                    };
                    ?>

                    <span class="status-badge <?php echo $payment_badge_class; ?>">
                        <?php echo htmlspecialchars(
                            $payment_status,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>
                </p>



                <p>

                    <strong>Payment method:</strong>

                    <?php echo htmlspecialchars(
                        $order["payment_method"] ?? "Not specified",
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>

                </p>


               
                <p>
                    <strong>Fulfilment status:</strong>

                    <?php
                    $fulfilment_badge_class = strtolower($order["status"]);
                    ?>

                    <span class="status-badge <?php echo htmlspecialchars(
                        $fulfilment_badge_class,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>">
                        <?php echo htmlspecialchars(
                            $order["status"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>
                </p>



                <?php if (
                    $order["status"] !== "Cancelled" &&
                    $order["status"] !== "Completed"
                ): ?>

                    <form
                        method="POST"
                        action="orders.php"
                    >

                        <?php echo csrf_field(); ?>

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?php echo (int)$order["id"]; ?>"
                        >


                        <label
                            for="status-<?php echo (int)$order["id"]; ?>"
                        >
                            Update fulfilment status:
                        </label>


                        <select
                            id="status-<?php echo (int)$order["id"]; ?>"
                            name="status"
                            required
                        >

                            <?php
								$statuses = [
									"Pending",
									"Processing",
									"Shipped",
									"Completed",
									"Cancelled"
								];

								foreach ($statuses as $status):

									// Shipped and Completed require confirmed payment.
									if (
										($status === "Shipped" || $status === "Completed") &&
										$order["payment_status"] !== "Paid"
									) {
										continue;
									}

								?>
									<option
										value="<?php echo htmlspecialchars(
											$status,
											ENT_QUOTES,
											"UTF-8"
										); ?>"
										<?php echo $order["status"] === $status
											? "selected"
											: ""; ?>
									>
										<?php echo htmlspecialchars(
											$status,
											ENT_QUOTES,
											"UTF-8"
										); ?>
									</option>

								<?php endforeach; ?>

                        </select>


                        <button
                            type="submit"
                            name="update_status"
                        >
                            Save Status
                        </button>

                    </form>

                <?php else: ?>

                    <p>
                        <strong>
                            This order can no longer be modified.
                        </strong>
                    </p>

                <?php endif; ?>


                <p>

                    <a
                        href="view_order.php?id=<?php echo (int)$order["id"]; ?>"
                    >
                        View Order Details
                    </a>

                </p>

            </section>

        <?php endwhile; ?>

    <?php else: ?>

        <p>
            No orders have been placed yet.
        </p>

    <?php endif; ?>

</main>

    <script src="../js/admin-orders.js" defer></script>
    
</body>

</html>