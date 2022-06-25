<?php

namespace hati;

/**
 * Perok is a wrapper class around PHP Mailer library. The class has singleton pattern
 * implemented for providing a simple line API to the client code. It uses SMTP protocol
 * of google. Configuration for this such as email & password can be set using
 * <b>HatiConfig.</b> file. If the google account has two factor authentication turned on,
 * then it needs a one time 16 digit password from the google account to be set in the config
 * file.
 *
 * Many powerful emailing feature such as attachment, HTML email, CID image in HTML, BCC are
 * well supported by this class.
 * */

use hati\trunk\TrunkErr;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Perok {

    // single ton instance of this class
    private static ?Perok $INS = null;

    // PHP Mailer object which does all the emailing
    private PHPMailer $phpMailer;

    // singleton pattern
    private static function get(): Perok {
        if (self::$INS == null) self::$INS = new Perok();
        return self::$INS;
    }

    private function __construct() {
        // initialization of the PHP Mailer
        $this -> phpMailer = new PHPMailer();
        $this -> phpMailer -> isSMTP();
        $this -> phpMailer -> SMTPAuth = true;
        $this -> phpMailer -> SMTPSecure = 'tls';

        // initialize google account SMTP protocol details
        $this -> phpMailer -> Host = 'smtp.gmail.com';
        $this -> phpMailer -> Port = Hati::mailerPort();
        $this -> phpMailer -> Username = Hati::mailerEmail();
        $this -> phpMailer -> Password = Hati::mailerPass();

        // set up mailing profile
        try {
            $this -> phpMailer -> setFrom(Hati::mailerEmail(), Hati::mailerName());
        } catch (Exception $e) {
            throw new TrunkErr('Perok encountered error during initialization: ' . $e -> getMessage());
        }
    }

    /**
     * This gets called everytime sending emails to the recipients in order
     * to send to & clear the added emails were queued to avoid resending emails
     * to the same addresses again down the line in the code.
     *
     * @throws Exception
     */
    private static function phpMailerSend(Perok $perok, string $subject): bool {
        $perok -> phpMailer -> Subject = $subject;
        $sent = $perok -> phpMailer -> send();
        $perok -> phpMailer -> clearAllRecipients();
        return $sent;
    }

    /**
     * Any ordinary textual message can be composed using this method.
     *
     * @param string $msg The message of the email body with no HTML markup in it.
     * */
    public static function composeText(string $msg): void {
        $ins = self::get();
        $ins -> phpMailer -> isHTML(false);
        $ins -> phpMailer -> Body = $msg;
    }

    /**
     * Message containing any HTML markup should be composed using this method. It sets
     * the header for the content type as text/html for correct interpretation in the
     * client side.
     *
     * @param string $msg The message containing HTML markup to be sent.
     * */
    public static function composeHtml(string $msg): void {
        $ins = self::get();
        $ins -> phpMailer -> isHTML();
        $ins -> phpMailer -> Body = $msg;
    }

    /**
     * This method eases the composing HTML email by getting the contents from an HTML file.
     * The file path is the full name of the file with extension and directory appended in
     * front inside the server document root. Perok does the server root directory path
     * appending by using @link Hati::docRoot() method internally.
     *
     * @param string $filePath The file name with extension and path within the server root folder.
     * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
     *
     * @return bool Returns true if it can successfully compose message body from the HTML file.
     * */
    public static function composeFromHtml(string $filePath, bool $throwErr = false): bool {
        $ins = self::get();
        $ins -> phpMailer -> isHTML();
        $path = Hati::neutralizeSeparator(Hati::docRoot(). $filePath);
        try {
            $ins -> phpMailer -> msgHTML(file_get_contents($path));
            return true;
        } catch (Exception $e) {
            if ($throwErr) throw new TrunkErr('Failed composing HTML page as body: ' . $e -> getMessage());
            return false;
        }
    }

    /**
     * In case of any restriction upon or failure of loading an HTML email by the client,
     * an only textual alternative message can be added within the email.
     *
     * @param string $msg Textual representation of the HTML email.
     * */
    public static function altEmail(string $msg) {
        $ins = self::get();
        $ins -> phpMailer -> AltBody = $msg;
    }

    /**
     * Any file can be composed as part of attachment of the email. The file path is the full
     * name of the file with extension and directory appended in front inside the server document
     * root. Perok does the server root directory path appending by using @link Hati::docRoot()
     * method internally.
     *
     * @param string $filePath The file name with extension and path within the server root folder.
     * @param string $fileName File name as it will be shown in the email client.
     * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
     * */
    public static function attachFile(string $filePath, string $fileName = '', bool $throwErr = false) {
        $ins = self::get();
        $path = Hati::neutralizeSeparator(Hati::docRoot() . $filePath);
        try {
            $ins -> phpMailer -> addAttachment($path, $fileName);
        } catch (Exception $e) {
            if ($throwErr) throw new TrunkErr('Failed attaching file: ' . $e -> getMessage());
        }
    }

    /**
     * This method can embed any image for including images as CID image in the HTML message.
     * The path to the image file is only with the directory and full name with extension. Perok
     * does the server root directory path appending by using @link Hati::docRoot() method internallly.
     *
     * @param string $filePath The file name with extension and path within the server root folder.
     * @param string $fileName Argument for CID image to be used inside the HTML message.
     * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
     * */
    public static function embedCIDImage(string $filePath, string $fileName, bool $throwErr = false): void {
        $ins = self::get();
        try {
            $path = Hati::neutralizeSeparator(Hati::docRoot() . $filePath);
            $ins -> phpMailer -> addEmbeddedImage($path, $fileName);
        } catch (Exception $e) {
            if ($throwErr) throw new TrunkErr('Failed embedding image: ' . $e -> getMessage());
        }
    }

    /**
     * A single message with a subject can be sent using send method. It does the same as sendBulk.
     * The only difference is that in bulk emailing we don't have custom email subject. However, for
     * a single email we could have. That is why this message was added to Perok for convenient.
     *
     * It internally uses @link phpMailerSend for setting the subject and sending the email.
     *
     * @param string $to The email address of the recipient.
     * @param string $subject The subject for the email.
     * @param string $replyTo The email for reply-to value.
     * @param bool $throwErr Indicate whether to throw exception upon encountering error.
     *
     * @return bool Returns true when message was sent to the recipient; false otherwise.
     * */
    public static function send(string $to, string $subject, string $replyTo = '', bool $throwErr = false): bool {
        try {
            $perok = self::get();

            // get the reply to address either from the argument or the configuration file
            $replyTo = empty($replyTo) ? Hati::mailerReplyTo() : $replyTo;
            if (!empty($replyTo)) $perok -> phpMailer -> addReplyTo($replyTo);

            $perok -> phpMailer -> addAddress($to);
            return self::phpMailerSend($perok, $subject);
        } catch (Exception $e) {
            if ($throwErr) throw new TrunkErr('Failed sending email: ' . $e -> getMessage());
            return false;
        }
    }

    /**
     * A single message can be send to many email recipients using sengBulk method. It should
     * be used when both the subject & the message remains the same. The addresses argument
     * is type of string where emails are separated by comma(,). The message is sent as BCC
     * to the recipients.
     *
     * It internally uses @link phpMailerSend for setting the subject and sending the email.
     *
     * @param string $addresses Comma seperated emails as recipients.
     * @param string $subject The subject which stays same for all the recipients.
     * @param bool $throwErr Indicate whether to throw exception upon encountering error.
     *
     * @return bool Returns true when message was sent to all the recipients; false otherwise.
     * */
    public static function sendBulk(string $addresses, string $subject = '', bool $throwErr = false): bool {
        try {
            $perok = self::get();
            $emails = explode(',', $addresses);
            foreach ($emails as $email) $perok -> phpMailer -> addBCC($email);
            return self::phpMailerSend($perok, $subject);
        } catch (Exception $e) {
            if ($throwErr) throw new TrunkErr('Failed sending bulk emails: ' . $e -> getMessage());
            return false;
        }
    }

    /**
     * Besides all the fancy features from PHP Mailer, the Perok provides with standard
     * php mail function. It is very basic way of sending email. It has header of text/html
     * meaning that it sends emails as HTML.
     *
     * @param string $to The address of the recipient.
     * @param string $subject Email subject.
     * @param string $message The actual email optionally including any HTML markup.
     *
     * @return  bool Returns true on successful sending email; false otherwise.
     * */
    public static function sendStd(string $to, string $subject, string $message): bool {
        $nameEmail = Hati::mailerName() . ' <' . Hati::mailerEmail() . '>';
        $header  = "From: $nameEmail\n";
        $header .= "X-Sender: $nameEmail\n";
        $header .= 'X-Mailer: PHP/' . phpversion();
        $header .= "X-Priority: 1\n"; // Urgent message!
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-Type: text/html; charset=UTF-8\n";

        return mail($to, $subject, $message, $header);
    }

}