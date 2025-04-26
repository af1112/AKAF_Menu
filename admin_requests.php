<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

// Load requests
$stmt = $conn->prepare("SELECT r.id, r.order_id, r.user_id, r.request_text, r.response_text, r.created_at, r.status, u.username, o.table_number FROM order_requests r JOIN users u ON r.user_id = u.id JOIN orders o ON r.order_id = o.id ORDER BY r.created_at DESC");
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Respond to request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond'])) {
    $request_id = intval($_POST['request_id']);
    $response_text = trim($_POST['response_text']);
    if (!empty($response_text)) {
        $stmt_respond = $conn->prepare("UPDATE order_requests SET response_text = ?, status = 'responded', responded_at = NOW() WHERE id = ?");
        $stmt_respond->bind_param("si", $response_text, $request_id);
        $stmt_respond->execute();
        header("Location: admin_requests.php");
        exit();
    }
}
$stmt_calls = $conn->prepare("SELECT wc.id, wc.order_id, wc.user_id, wc.call_time, wc.attended_time, wc.status, u.username, o.table_number FROM waiter_calls wc JOIN users u ON wc.user_id = u.id JOIN orders o ON wc.order_id = o.id ORDER BY wc.call_time DESC");
$stmt_calls->execute();
$waiter_calls = $stmt_calls->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark as attended
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attend_call'])) {
    $call_id = intval($_POST['call_id']);
    $stmt_attend = $conn->prepare("UPDATE waiter_calls SET status = 'attended', attended_time = NOW() WHERE id = ?");
    $stmt_attend->bind_param("i", $call_id);
    $stmt_attend->execute();
    header("Location: admin_requests.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Manage Requests</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Table</th>
                    <th>User</th>
                    <th>Request</th>
                    <th>Response</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?php echo $request['order_id']; ?></td>
                        <td><?php echo $request['table_number']; ?></td>
                        <td><?php echo htmlspecialchars($request['username']); ?></td>
                        <td><?php echo htmlspecialchars($request['request_text']); ?></td>
                        <td><?php echo htmlspecialchars($request['response_text'] ?? ''); ?></td>
                        <td><?php echo $request['status']; ?></td>
                        <td><?php echo $request['created_at']; ?></td>
                        <td>
                            <?php if ($request['status'] === 'pending'): ?>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <textarea name="response_text" class="form-control" rows="2"></textarea>
                                    <button type="submit" name="respond" class="btn btn-primary mt-2">Respond</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
		<h2>Waiter Calls</h2>
		<table class="table">
			<thead>
				<tr>
					<th>Order ID</th>
					<th>Table</th>
					<th>User</th>
					<th>Call Time</th>
					<th>Status</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($waiter_calls as $call): ?>
					<tr>
						<td><?php echo $call['order_id']; ?></td>
						<td><?php echo $call['table_number']; ?></td>
						<td><?php echo htmlspecialchars($call['username']); ?></td>
						<td><?php echo $call['call_time']; ?></td>
						<td><?php echo $call['status']; ?></td>
						<td>
							<?php if ($call['status'] === 'called'): ?>
								<form method="POST">
									<input type="hidden" name="call_id" value="<?php echo $call['id']; ?>">
									<button type="submit" name="attend_call" class="btn btn-success">Mark as Attended</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
    </div>
</body>
</html>