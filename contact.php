<?php
// contact.php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session
session_start();

// Define the path to the success page
define('SUCCESS_PAGE', 'contact_success.php');

// Include required files with error handling
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/send_email.php';
    require_once __DIR__ . '/vendor/autoload.php';
} catch (Exception $e) {
    error_log("Failed to include required files: " . $e->getMessage());
    die("Error: Server configuration issue. Please check server logs.");
}

// Initialize messages from session if redirected back (e.g., after an error)
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear session messages after displaying them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Initialize form fields for sticky form
$name = $_SESSION['form_data']['name'] ?? '';
$email = $_SESSION['form_data']['email'] ?? '';
$subject = $_SESSION['form_data']['subject'] ?? '';
$message = $_SESSION['form_data']['message'] ?? '';

// Clear form data from session
unset($_SESSION['form_data']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $subject = trim(htmlspecialchars($_POST['subject'] ?? ''));
    $message = trim(htmlspecialchars($_POST['message'] ?? ''));

    // Store form data temporarily in session for re-population on error
    $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message];

    // Server-side validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['error_message'] = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Please enter a valid email address.';
    }

    // Check if there was a validation error before proceeding
    if (isset($_SESSION['error_message'])) {
        // Redirect back to this page to show error (PRG pattern)
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        // --- 1. Store in database ---
        $stmt = $conn->prepare("
            INSERT INTO contact_messages (name, email, subject, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        $stmt->execute();
        $stmt->close();

        // --- 2. Send email to user ---
        $user_email_data = [
            'to_email' => $email,
            'to_name' => $name,
            'reply_to' => FROM_EMAIL,
            'reply_to_name' => FROM_NAME,
            'subject' => 'Thank You for Contacting ' . SITE_NAME,
            'template_path' => __DIR__ . '/includes/templates/email_contact_user.php',
            'template_data' => [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message
            ],
            'alt_body' => "Dear $name,\n\nThank you for contacting " . SITE_NAME . ". We have received your message and will get back to you soon.\n\nBest regards,\n" . SITE_NAME
        ];
        // The sendEmail function already handles its own logging/error_log
        sendEmail($user_email_data, 'contact_user', "Contact confirmation sent to: $email");

        // --- 3. Send email to admin ---
        $admin_email_data = [
            'to_email' => 'admin@dgctransports.com',
            'to_name' => 'DGC Transports Admin',
            'subject' => 'New Contact Form Submission - ' . SITE_NAME,
            'template_path' => __DIR__ . '/includes/templates/email_contact_admin.php',
            'template_data' => [
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message
            ],
            'alt_body' => "New contact form submission:\n\nName: $name\nEmail: $email\nSubject: $subject\nMessage: $message\n\nReceived at: " . date('Y-m-d H:i:s')
        ];
        sendEmail($admin_email_data, 'contact_admin', 'Contact notification sent to admin');

        // Set success message in session and redirect to success page
        $_SESSION['success_message'] = 'Thank you for your message! We will get back to you soon.';
        // Clear sticky form data on success
        unset($_SESSION['form_data']); 
        
        header('Location: ' . SUCCESS_PAGE);
        exit;

    } catch (Exception $e) {
        error_log("Error processing contact form: " . $e->getMessage());
        $_SESSION['error_message'] = 'An error occurred while sending your message. Please try again later.';
        
        // Redirect back to this page to show error (PRG pattern)
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Create contact_messages table if it doesn't exist
try {
    $result = $conn->query("SHOW TABLES LIKE 'contact_messages'");
    if ($result->num_rows === 0) {
        $create_table = "
            CREATE TABLE contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('new', 'read', 'responded') DEFAULT 'new'
            )
        ";
        $conn->query($create_table);
        error_log("Created contact_messages table");
    }
} catch (Exception $e) {
    error_log("Failed to create contact_messages table: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray-bg: #f5f5f5;
            --light-gray-border: #e5e7eb;
        }
        body {
            background: linear-gradient(135deg, var(--white), var(--gray-bg));
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1280px;
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-1px);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .input-field {
            border: 2px solid var(--light-gray-border);
            border-radius: 8px;
            padding: 10px 12px;
            width: 100%;
            color: var(--black);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-size: 1rem;
            line-height: 1.5;
            box-sizing: border-box;
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 8px rgba(227, 6, 19, 0.2);
        }
        .error-message, .success-message {
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            font-weight: 500;
            padding: 8px;
            border-radius: 4px;
        }
        .error-message {
            color: var(--primary-red);
            border-left: 4px solid var(--primary-red);
            background-color: #ffeaea;
        }
        .success-message {
            color: #15803d;
            border-left: 4px solid #15803d;
            background-color: #f0fdf4;
        }
        .error-message i, .success-message i {
            margin-right: 8px;
        }
        .hero-image {
            background-image: url("<?= defined('SITE_URL') ? SITE_URL : 'https://booking.dgctransports.com' ?>/assets/images/suv.jpg");
            background-size: cover;
            background-position: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        .spinner {
            display: none;
        }
        .spinner-active {
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php
    try {
        require_once __DIR__ . '/templates/header.php';
    } catch (Exception $e) {
        error_log("Failed to include header.php: " . $e->getMessage());
        echo '<div class="error-message"><i class="fas fa-exclamation-circle"></i>Failed to load header. Please contact support.</div>';
    }
    ?>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="container w-full">
            <div class="card overflow-hidden fade-in">
                <div class="grid lg:grid-cols-2 gap-0">
                    <div class="p-8 lg:p-12">
                        <div class="max-w-md mx-auto">
                            <div class="text-center mb-8">
                                <h1 class="text-4xl font-bold text-gray-800">
                                    Contact <span class="text-primary-red">Us</span>
                                </h1>
                                <p class="text-gray-600 text-lg">We’re here to assist you with any questions</p>
                            </div>
                            <?php if ($error_message): ?>
                                <p class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></p>
                            <?php endif; ?>
                            
                            <form id="contactForm" action="contact.php" method="POST" class="space-y-6">
                                <div class="form-group">
                                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-user text-primary-red mr-2"></i>
                                        Name
                                    </label>
                                    <input type="text" class="input-field" id="name" name="name" required aria-label="Full name" value="<?= htmlspecialchars($name) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-envelope text-primary-red mr-2"></i>
                                        Email
                                    </label>
                                    <input type="email" class="input-field" id="email" name="email" required aria-label="Email address" value="<?= htmlspecialchars($email) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="subject" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-comment text-primary-red mr-2"></i>
                                        Subject
                                    </label>
                                    <input type="text" class="input-field" id="subject" name="subject" required aria-label="Subject" value="<?= htmlspecialchars($subject) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="message" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-pen text-primary-red mr-2"></i>
                                        Message
                                    </label>
                                    <textarea class="input-field" id="message" name="message" rows="5" required aria-label="Message"><?= htmlspecialchars($message) ?></textarea>
                                </div>
                                <button type="submit" id="submitButton" class="btn-primary w-full mt-4">
                                    <i class="fas fa-spinner fa-spin spinner mr-2"></i>
                                    <span class="button-text">Send Message</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="hero-image relative hidden lg:flex items-center justify-center">
                        <div class="absolute inset-0 bg-black opacity-50"></div>
                        <div class="relative z-10 p-8 text-center text-white">
                            <i class="fas fa-headset text-8xl text-primary-red mb-6"></i>
                            <h2 class="text-4xl font-bold mb-4">We’re Here to Help</h2>
                            <p class="text-xl text-gray-200 mb-6">Reach out to our support team for any assistance</p>
                            <div class="grid grid-cols-1 gap-6 max-w-sm mx-auto">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-envelope text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">Email Support</h3>
                                        <p class="text-gray-300 text-sm">admin@dgctransports.com</p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-phone text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">Phone Support</h3>
                                        <p class="text-gray-300 text-sm">Available 24/7</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    try {
        require_once __DIR__ . '/templates/footer.php';
    } catch (Exception $e) {
        error_log("Failed to include footer.php: " . $e->getMessage());
        echo '<footer class="py-4 text-center text-gray-500 text-sm">© ' . date('Y') . ' DGC Transports. All rights reserved.</footer>';
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('contactForm');
            const submitButton = document.getElementById('submitButton');
            const spinner = submitButton.querySelector('.spinner');
            const buttonText = submitButton.querySelector('.button-text');

            if (form) {
                form.addEventListener('submit', function(e) {
                    const name = document.getElementById('name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const subject = document.getElementById('subject').value.trim();
                    const message = document.getElementById('message').value.trim();

                    // Clear previous error messages
                    document.querySelectorAll('.temp-error').forEach(el => el.remove());

                    // Client-side validation
                    if (!name || !email || !subject || !message) {
                        e.preventDefault();
                        showError('Please fill in all required fields.');
                        return false;
                    }

                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        e.preventDefault();
                        showError('Please enter a valid email address.');
                        return false;
                    }

                    // Show spinner, change button text, and disable button
                    submitButton.disabled = true;
                    spinner.classList.add('spinner-active');
                    buttonText.textContent = 'Sending...';
                });

                function showError(message) {
                    const formContainer = form.parentNode;
                    const errorDiv = document.createElement('p');
                    errorDiv.className = 'error-message temp-error';
                    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i>${message}`;
                    form.parentNode.insertBefore(errorDiv, form);
                    setTimeout(() => errorDiv.remove(), 5000);
                    // Reset button state
                    submitButton.disabled = false;
                    spinner.classList.remove('spinner-active');
                    buttonText.textContent = 'Send Message';
                }
            }
        });
    </script>
</body>
</html>