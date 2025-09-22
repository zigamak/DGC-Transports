<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Require login
requireLogin();

// Get current user
$user = $_SESSION['user'];

// Fetch user's bookings (by user_id OR email)
$stmt = $conn->prepare("
    SELECT 
        b.id, b.pnr, b.passenger_name, b.trip_date, b.seat_number, b.total_amount, b.payment_status, b.status,
        p.name AS pickup_city, d.name AS dropoff_city,
        ts.departure_time, ts.arrival_time,
        vt.type AS vehicle_type
    FROM bookings b
    JOIN trip_templates tt ON b.template_id = tt.id
    JOIN cities p ON tt.pickup_city_id = p.id
    JOIN cities d ON tt.dropoff_city_id = d.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    WHERE b.user_id = ? OR b.email = ?
    ORDER BY b.trip_date DESC, ts.departure_time DESC
");
$stmt->bind_param("is", $user['id'], $user['email']);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard - DGC Transports</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
      .dashboard-card { max-width: 1200px; }
      .btn-copy {
          display: flex;
          align-items: center;
          background: linear-gradient(to right, #2563eb, #1d4ed8);
          color: white;
          font-weight: 600;
          padding: 0.5rem 1rem;
          border-radius: 0.75rem;
          cursor: pointer;
          transition: all 0.2s;
      }
      .btn-copy:hover {
          transform: scale(1.05);
          background: linear-gradient(to right, #1d4ed8, #2563eb);
      }
  </style>
</head>
<body class="bg-gray-100">
  <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full dashboard-card mx-auto">
      
      <!-- Welcome Section -->
      <div class="bg-white rounded-3xl shadow-2xl p-8 mb-8">
        <div class="text-center mb-6">
          <h1 class="text-4xl font-bold text-gray-900 mb-2">
            Welcome, <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
          </h1>
          <p class="text-gray-600 text-lg">Your DGC Transports Dashboard</p>
        </div>
        
        <!-- Affiliate Section -->
        <div class="bg-blue-50 rounded-xl p-6 mb-6">
          <h2 class="text-2xl font-bold text-blue-700 mb-4">
            <i class="fas fa-link mr-2"></i>Affiliate Program
          </h2>
          <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
              <p class="text-gray-700 mb-2">
                Your unique affiliate ID: 
                <span id="affiliateId" class="font-mono bg-gray-200 px-2 py-1 rounded">
                  <?= htmlspecialchars($user['affiliate_id'] ?? 'Not generated') ?>
                </span>
              </p>
              <p class="text-gray-600">
                Share this ID to earn credits on referrals. Current credits: 
                <span class="font-bold"><?= number_format($user['credits'] ?? 0, 2) ?> NGN</span>
              </p>
            </div>
            <button id="copyBtn" class="btn-copy">
              <i class="fas fa-copy mr-2"></i> <span>Copy ID</span>
            </button>
          </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <a href="book_trip.php" class="bg-gradient-to-r from-blue-600 to-blue-800 text-white font-bold py-4 px-6 rounded-xl text-center hover:scale-105 transition shadow-lg">
            <i class="fas fa-bus mr-2"></i>Book a Trip
          </a>
          <a href="profile.php" class="bg-gradient-to-r from-green-500 to-green-700 text-white font-bold py-4 px-6 rounded-xl text-center hover:scale-105 transition shadow-lg">
            <i class="fas fa-user-edit mr-2"></i>Update Profile
          </a>
          <a href="logout.php" class="bg-gradient-to-r from-red-500 to-red-700 text-white font-bold py-4 px-6 rounded-xl text-center hover:scale-105 transition shadow-lg">
            <i class="fas fa-sign-out-alt mr-2"></i>Logout
          </a>
        </div>
      </div>

      <!-- Bookings Section -->
      <div class="bg-white rounded-3xl shadow-2xl p-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-6">
          <i class="fas fa-ticket-alt mr-2"></i>Your Bookings
        </h2>

        <?php if (empty($bookings)): ?>
          <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg mb-6">
            <p>No bookings found. Book your first trip today!</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 rounded-xl overflow-hidden shadow">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PNR</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Route</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Time</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Seat</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($bookings as $booking): ?>
                  <tr class="<?= $booking['status'] === 'cancelled' ? 'bg-red-50' : ($booking['status'] === 'confirmed' ? 'bg-green-50' : '') ?>">
                    <td class="px-6 py-4 whitespace-nowrap font-mono"><?= htmlspecialchars($booking['pnr']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= date('d M Y', strtotime($booking['trip_date'])) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?>
                      <br><small class="text-gray-500"><?= htmlspecialchars($booking['vehicle_type']) ?></small>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      Dep: <?= date('H:i', strtotime($booking['departure_time'])) ?>
                      <br>Arr: <?= date('H:i', strtotime($booking['arrival_time'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= $booking['seat_number'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= number_format($booking['total_amount'], 2) ?> NGN</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 py-1 rounded-full text-xs font-semibold
                        <?= $booking['status'] === 'confirmed' ? 'bg-green-200 text-green-800' :
                            ($booking['status'] === 'pending' ? 'bg-yellow-200 text-yellow-800' : 'bg-red-200 text-red-800') ?>">
                        <?= ucfirst($booking['status']) ?>
                      </span>
                      <br>
                      <small><?= ucfirst($booking['payment_status']) ?></small>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap space-x-2">
                      <a href="view_booking.php?id=<?= $booking['id'] ?>" class="text-blue-600 hover:underline">
                        <i class="fas fa-eye"></i> View
                      </a>
                      <?php if ($booking['status'] !== 'cancelled' && strtotime($booking['trip_date']) > time()): ?>
                        | <a href="cancel_booking.php?id=<?= $booking['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Are you sure?')">
                          <i class="fas fa-times"></i> Cancel
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    const copyBtn = document.getElementById("copyBtn");
    const affiliateId = document.getElementById("affiliateId").innerText;

    copyBtn.addEventListener("click", () => {
      navigator.clipboard.writeText(affiliateId).then(() => {
        copyBtn.querySelector("span").innerText = "Copied!";
        copyBtn.querySelector("i").classList.remove("fa-copy");
        copyBtn.querySelector("i").classList.add("fa-check");
        setTimeout(() => {
          copyBtn.querySelector("span").innerText = "Copy ID";
          copyBtn.querySelector("i").classList.remove("fa-check");
          copyBtn.querySelector("i").classList.add("fa-copy");
        }, 2000);
      });
    });
  </script>
</body>
</html>
