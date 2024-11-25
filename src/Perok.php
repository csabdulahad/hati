<?php

namespace hati;

use hati\config\Key;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Perok is a wrapper class around PHP Mailer library. The class has singleton pattern
 * implemented for providing a simple line API to the client code. It uses SMTP protocol
 *
 * Protocol configuration for this such as email & password can be set using
 * <b>hati/hati.json.</b> file.
 *
 * Google SMTP can be used too. If the google account has two factor authentication turned on,
 * then it needs a one time 16 digit password from the google account to be set in the config
 * file.
 *
 * Many powerful emailing feature such as attachment, HTML email, CID image in HTML, BCC are
 * well supported by this class.
 * */

class Perok {

	// single ton instance of this class
	private static ?Perok $INS = null;

	// PHP Mailer object which does all the emailing
	private PHPMailer $phpMailer;

	// Catch the sender name reading from the config
	private string $fromName;

	// Catch the sender email reading from the config
	private string $fromEmail;

	// singleton pattern
	private static function get(): Perok {
		if (self::$INS == null) self::$INS = new Perok();
		return self::$INS;
	}

	private function __construct() {
		// initialization of the PHP Mailer
		$this -> phpMailer = new PHPMailer();
		$this -> phpMailer -> CharSet = PHPMailer::CHARSET_UTF8;
		$this -> phpMailer -> isSMTP();
		$this -> phpMailer -> SMTPAuth = true;
		$this -> phpMailer -> SMTPSecure = 'tls';

		// initialize google account SMTP protocol details
		$this -> phpMailer -> Host = Hati::config(Key::MAILER_HOST);
		$this -> phpMailer -> Port = Hati::config(Key::MAILER_PORT, 'int');
		$this -> phpMailer -> Username = Hati::config(Key::MAILER_EMAIL);
		$this -> phpMailer -> Password = Hati::config(Key::MAILER_PASS);

		$this -> fromName = Hati::config(Key::MAILER_NAME);
		$this -> fromEmail = Hati::config(Key::MAILER_EMAIL);

		// set up mailing profile
		try {
			$this -> phpMailer -> setFrom($this -> fromEmail, $this -> fromName);
		} catch (Exception $e) {
			throw new Trunk('Perok encountered error during initialization: ' . $e -> getMessage());
		}
	}

	/**
	 * Adds emails as Binary Carbon Copy (BCC)
	 *
	 * @param array|string $email The email or array of emails
	 * @throws Exception if fails to add emails as BCC
	 */
	public static function bcc(array|string ...$email): void {
		$perok = self::get();

		foreach ($email as $e) {
			if (is_array($e)) {
				foreach ($e as $ee) $perok -> phpMailer -> addBCC($ee);
				continue;
			}

			$perok -> phpMailer -> addBCC($e);
		}
	}

	/**
	 * Adds emails as Carbon Copy (CC)
	 *
	 * @param array|string $email The email or array of emails
	 * @throws Exception if fails to add emails as CC
	 */
	public static function cc(array|string ...$email): void {
		$perok = self::get();

		foreach ($email as $e) {
			if (is_array($e)) {
				foreach ($e as $ee) $perok -> phpMailer -> addCC($ee);
				continue;
			}

			$perok -> phpMailer -> addCC($e);
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
		$perok -> phpMailer -> clearBCCs();
		$perok -> phpMailer -> clearCCs();

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
	 *
	 * Use helper methods {@link Hati::root()} & {@link Hati::projectRoot()} to explicitly
	 * get the path to any html file.
	 *
	 * @param string $filePath The path to the html file.
	 * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
	 *
	 * @return bool Returns true if it can successfully compose message body from the HTML file.
	 */
	public static function composeFromHtml(string $filePath, bool $throwErr = false): bool {
		$ins = self::get();
		$ins -> phpMailer -> isHTML();
		try {
			$ins -> phpMailer -> msgHTML(file_get_contents($filePath));
			return true;
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed composing HTML page as body: ' . $e -> getMessage());
			return false;
		}
	}

	/**
	 * In case of any restriction upon or failure of loading an HTML email by the client,
	 * an only textual alternative message can be added within the email.
	 *
	 * @param string $msg Textual representation of the HTML email.
	 * */
	public static function altEmail(string $msg): void {
		$ins = self::get();
		$ins -> phpMailer -> AltBody = $msg;
	}

	/**
	 * Any file can be composed as part of attachment of the email.
	 *
	 * Use helper methods {@link Hati::root()} & {@link Hati::projectRoot()} to explicitly
	 * get the path to the file.
	 *
	 * @param string $filePath The path to the file to be sent as attachment.
	 * @param string $fileName File name as it will be shown in the email client.
	 * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
	 */
	public static function attachFile(string $filePath, string $fileName = '', bool $throwErr = false): void {
		$ins = self::get();
		try {
			$ins -> phpMailer -> addAttachment($filePath, $fileName);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed attaching file: ' . $e -> getMessage());
		}
	}

	/**
	 * Any file contents in string format can be attached in email by this method.
	 *
	 * @param string $fileAsStr The file value which is in string format.
	 * @param string $fileName File name, preferably with extension.
	 * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
	 * */
	public static function attachStr(string $fileAsStr, string $fileName, bool $throwErr = false): void	{
		$ins = self::get();
		try {
			$ins -> phpMailer -> addStringAttachment($fileAsStr, $fileName);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed to attach string as file: ' . $e -> getMessage());
		}
	}

	/**
	 * This method can embed any image for including images as CID image in the HTML message.
	 *
	 *  Use helper methods {@link Hati::root()} & {@link Hati::projectRoot()} to explicitly
	 *  get the path to the image file.
	 *
	 * @param string $filePath The path to the image file
	 * @param string $fileName Argument for CID image to be used inside the HTML message.
	 * @param bool $throwErr Indicate whether to throw exception upon encountering any error.
	 *
	 */
	public static function embedCIDImage(string $filePath, string $fileName, bool $throwErr = false): void {
		$ins = self::get();
		try {
			$ins -> phpMailer -> addEmbeddedImage($filePath, $fileName);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed embedding image: ' . $e -> getMessage());
		}
	}

	/**
	 * A single message with a subject can be sent using send method. It does the same as sendBulk.
	 * The only difference is that in bulk emailing we don't have custom email subject. However, for
	 * a single email we could have. That is why this message was added to Perok for convenient.
	 *
	 * It internally uses {@link phpMailerSend} for setting the subject and sending the email.
	 *
	 * @param string $to The email address of the recipient.
	 * @param string $subject The subject for the email.
	 * @param string $replyTo The email for reply-to value.
	 * @param string $from The sender of the email to override the project configured sender name.
	 * @param bool $throwErr Indicate whether to throw exception upon encountering error.
	 *
	 * @return bool Returns true when message was sent to the recipient; false otherwise.
	 * */
	public static function send(string $to, string $subject, string $replyTo = '', string $from = '', bool $throwErr = false): bool {
		try {
			$perok = self::get();

			if (!empty($from)) $perok -> phpMailer -> setFrom($perok -> fromEmail, $from);

			// get the reply to address either from the argument or the configuration file
			$replyTo = empty($replyTo) ? Hati::config(Key::MAILER_REPLY_TO) : $replyTo;
			if (!empty($replyTo)) $perok -> phpMailer -> addReplyTo($replyTo);

			$perok -> phpMailer -> addAddress($to);
			self::phpMailerSend($perok, $subject);

			$err = $perok -> phpMailer -> ErrorInfo;
			if (!empty($err)) throw new Exception($err);

			return true;
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed sending email: ' . $e -> getMessage());
			return false;
		}
	}

	/**
	 * A single message can be send to many email recipients using sengBulk method. It should
	 * be used when both the subject & the message remains the same. The addresses argument
	 * is type of string where emails are separated by comma(,). The message is sent as BCC
	 * to the recipients.
	 *
	 * It internally uses {@link phpMailerSend} for setting the subject and sending the email.
	 *
	 * @param string $addresses Comma seperated emails as recipients.
	 * @param string $subject The subject which stays same for all the recipients.
	 * @param string $from The sender of the email to override the project configured sender name.
	 * @param bool $throwErr Indicate whether to throw exception upon encountering error.
	 *
	 * @return bool Returns true when message was sent to all the recipients; false otherwise.
	 * */
	public static function sendBulk(string $addresses, string $subject = '', string $from = '', bool $throwErr = false): bool {
		try {
			$perok = self::get();
			$emails = explode(',', $addresses);

			if (!empty($from)) $perok -> phpMailer -> setFrom($perok -> fromEmail, $from);

			foreach ($emails as $email) $perok -> phpMailer -> addBCC($email);
			return self::phpMailerSend($perok, $subject);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed sending bulk emails: ' . $e -> getMessage());
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
		$nameEmail = Hati::config(Key::MAILER_NAME) . ' <' . Hati::config(Key::MAILER_EMAIL) . '>';
		$header  = "From: $nameEmail\n";
		$header .= "X-Sender: $nameEmail\n";
		$header .= 'X-Mailer: PHP/' . phpversion();
		$header .= "X-Priority: 1\n"; // Urgent message!
		$header .= "MIME-Version: 1.0\r\n";
		$header .= "Content-Type: text/html; charset=UTF-8\n";

		return mail($to, $subject, $message, $header);
	}

}