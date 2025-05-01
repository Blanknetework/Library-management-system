<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

// Direct configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'orgconnect2025@gmail.com');
define('SMTP_PASSWORD', 'zayxqwihrpjajzaz');
define('SMTP_PORT', '587');
define('SMTP_FROM_NAME', 'QCU Library');
define('SMTP_FROM_EMAIL', 'orgconnect2025@gmail.com');

function sendOverdueNotification($studentEmail, $studentName, $bookTitle, $dueDate) {
    try {
        $mail = new PHPMailer(true);
        
        // Enable debug output
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            echo "<pre style='margin: 0; padding: 2px; background: #f0f0f0; border-bottom: 1px solid #ddd;'>";
            echo htmlspecialchars($str);
            echo "</pre>";
        };

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
        
        
        $mail->Body = "
            <div style='font-family: \"Segoe UI\", Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; line-height: 1.6; color: #333; background-color: #f9f9f9;'>
                <!-- Main Card Container -->
                <div style='background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 40px; margin-bottom: 20px;'>
                    <!-- Header -->
                    <div style='text-align: center; margin-bottom: 35px; padding-bottom: 25px; border-bottom: 2px solid #f0f0f0;'>
                        <h1 style='color: #1a237e; margin: 0; font-size: 28px; font-weight: 600;'>Library Book Overdue Notice</h1>
                    </div>

                    <!-- Greeting -->
                    <p style='font-size: 16px; margin-bottom: 25px;'>
                        <strong style='color: #1a237e;'>Dear {$studentName},</strong>
                    </p>
                    
                    <p style='color: #555; margin-bottom: 25px;'>This is to inform you that the following book is overdue:</p>
                    
                    <!-- Book Details Card -->
                    <div style='background-color: #f8f9ff; border-left: 4px solid #1a237e; border-radius: 8px; padding: 25px; margin: 25px 0;'>
                        <div style='margin-bottom: 15px;'>
                            <div style='font-weight: 600; color: #1a237e; margin-bottom: 5px;'>Book Title</div>
                            <div style='color: #555; font-size: 16px;'>{$bookTitle}</div>
                        </div>
                        <div>
                            <div style='font-weight: 600; color: #d32f2f; margin-bottom: 5px;'>Due Date</div>
                            <div style='color: #d32f2f; font-size: 16px;'>{$dueDate}</div>
                        </div>
                    </div>
                    
                    <p style='color: #555; margin-bottom: 25px;'>Please return the book as soon as possible to avoid any penalties.</p>
                    
                    <!-- Warning Box -->
                    <div style='background-color: #fef6f6; border: 1px solid #fdeded; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <p style='color: #d32f2f; margin: 0; display: flex; align-items: center;'>
                            <span style='font-weight: 600; margin-right: 10px;'>⚠️ Important Note:</span>
                            <span>Failure to return the book may result in library privileges being suspended.</span>
                        </p>
                    </div>

                    <p style='color: #555; margin-bottom: 30px;'>Thank you for your cooperation.</p>
                    
                    <!-- Signature -->
                    <div style='margin-top: 35px; padding-top: 25px; border-top: 2px solid #f0f0f0;'>
                        <p style='margin: 0; color: #555;'>Best regards,</p>
                        <p style='margin: 5px 0 0 0; color: #1a237e; font-weight: 600;'>" . SMTP_FROM_NAME . "</p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='text-align: center; padding: 20px; color: #666;'>
                    <p style='margin: 0; font-size: 13px; line-height: 1.5;'>
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

IMPORTANT NOTE: Failure to return the book may result in library privileges being suspended.

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