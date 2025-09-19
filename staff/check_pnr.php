<?php
// staff/check_pnr.php

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
// check_role('staff'); // Uncomment this to enforce role-based access
require_once '../templates/header.php';
?>

<div class="container mt-5">
    <h2>Check PNR Status</h2>
    <form action="" method="GET">
        <div class="input-group mb-3">
            <input type="text" class="form-control" placeholder="Enter PNR" name="pnr" required>
            <button class="btn btn-primary" type="submit">Check</button>
        </div>
    </form>

    <?php if (isset($_GET['pnr']) && !empty($_GET['pnr'])):
        $pnr = $_GET['pnr'];
        $stmt = $conn->prepare("
            SELECT 
                b.*, 
                c1.name AS pickup_city, 
                c2.name AS dropoff_city,
                v.type AS vehicle_type
            FROM bookings b
            JOIN cities c1 ON b.pickup_city_id = c1.id
            JOIN cities c2 ON b.dropoff_city_id = c2.id
            JOIN vehicle_types v ON b.vehicle_type_id = v.id
            WHERE b.pnr = ? AND b.departure_date = CURDATE()
        ");
        $stmt->bind_param("s", $pnr);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if ($booking): ?>
            <div class="card mt-4">
                <div class="card-header">
                    Booking Details for PNR: <?= htmlspecialchars($booking['pnr']) ?>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Passenger Name:</strong> <?= htmlspecialchars($booking['passenger_name']) ?></li>
                    <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($booking['email']) ?></li>
                    <li class="list-group-item"><strong>Route:</strong> <?= htmlspecialchars($booking['pickup_city']) ?> to <?= htmlspecialchars($booking['dropoff_city']) ?></li>
                    <li class="list-group-item"><strong>Vehicle:</strong> <?= htmlspecialchars($booking['vehicle_type']) ?></li>
                    <li class="list-group-item"><strong>Departure Date:</strong> <?= htmlspecialchars($booking['departure_date']) ?></li>
                    <li class="list-group-item"><strong>Status:</strong> <span class="badge bg-success"><?= htmlspecialchars($booking['status']) ?></span></li>
                </ul>
                <div class="card-body">
                    <form action="confirm_booking.php" method="POST">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <button type="submit" name="confirm" class="btn btn-success">Confirm Booking</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-4" role="alert">
                No booking found for this PNR for today's date.
            </div>
        <?php endif;
        $stmt->close();
    endif; ?>
</div>

<?php require_once '../templates/footer.php'; ?>
