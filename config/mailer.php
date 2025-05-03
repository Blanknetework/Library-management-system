<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $env_lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos($line, '#') === 0) {
            continue; 
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
            $value = substr($value, 1, -1);
        }
        
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Get values from environment variables
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'QCU Library');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');

// Also important - disable debug output in production
function sendOverdueNotification($studentEmail, $studentName, $bookTitle, $dueDate) {
    try {
        $mail = new PHPMailer(true);
        
      
        $mail->SMTPDebug = 0; // Disable debug output
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($studentEmail, $studentName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Library Book Overdue Notice';
        
        
        $logoPath = __DIR__ . '/../assets/images/QCU_Logo_2019.png';
        $mail->AddEmbeddedImage($logoPath, 'logo_cid', 'QCU_Logo.png', 'base64', 'image/png');
        
        $mail->Body = "
            <div style='font-family: \"Segoe UI\", Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; line-height: 1.6; color: #333;'>
                <!-- Header -->
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:logo_cid' alt='QCU Logo' style='max-width: 150px; margin-bottom: 15px;'>
                    <h1 style='color: #1a237e; margin: 0; font-size: 24px; font-weight: 600;'>Library Book Overdue Notice</h1>
                </div>

                <!-- Content -->
                <div style='background-color: #ffffff; border-radius: 8px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>
                        <strong style='color: #1a237e;'>Dear {$studentName},</strong>
                    </p>
                    
                    <p style='color: #555; margin-bottom: 20px;'>This is to inform you that the following book is overdue:</p>
                    
                    <!-- Book Details -->
                    <div style='background-color: #f8f9ff; border-radius: 6px; padding: 20px; margin: 20px 0;'>
                        <div style='margin-bottom: 15px;'>
                            <div style='font-weight: 600; color: #1a237e; margin-bottom: 5px;'>Book Title</div>
                            <div style='color: #555;'>{$bookTitle}</div>
                        </div>
                        <div>
                            <div style='font-weight: 600; color: #d32f2f; margin-bottom: 5px;'>Due Date</div>
                            <div style='color: #d32f2f;'>{$dueDate}</div>
                        </div>
                    </div>
                    
                    <p style='color: #555; margin-bottom: 20px;'>Please return the book as soon as possible to avoid any penalties.</p>
                    
                    <!-- Warning -->
                    <div style='background-color: #fff5f5; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                        <p style='color: #d32f2f; margin: 0;'>
                            <span style='font-weight: 600;'>⚠️ Important:</span> Failure to return the book may result in library privileges being suspended.
                        </p>
                    </div>

                    <p style='color: #555; margin-bottom: 20px;'>Thank you for your cooperation.</p>
                    
                    <!-- Signature -->
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                        <p style='margin: 0; color: #555;'>Best regards,</p>
                        <p style='margin: 5px 0 0 0; color: #1a237e; font-weight: 600;'>" . SMTP_FROM_NAME . "</p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='text-align: center; padding: 15px; color: #666; font-size: 12px;'>
                    <p style='margin: 0;'>
                        This is an automated message from the QCU Library Management System.<br>
                        Please do not reply to this email.
                    </p>
                </div>
            </div>
        ";
        
        // Plain text version
        $mail->AltBody = "
            Library Book Overdue Notice

            Dear {$studentName},

            This is to inform you that the following book is overdue:

            Book Title: {$bookTitle}
            Due Date: {$dueDate}

            Please return the book as soon as possible to avoid any penalties.

            IMPORTANT: Failure to return the book may result in library privileges being suspended.

            Thank you for your cooperation.

            Best regards,
            " . SMTP_FROM_NAME . "

            ---
            This is an automated message from the QCU Library Management System.
            Please do not reply to this email.
                    ";

        $mail->send();
        return [
            'success' => true,
            'message' => 'Notification sent successfully'
        ];
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return [
            'success' => false,
            'message' => "Failed to send notification: {$mail->ErrorInfo}"
        ];
    }
} 