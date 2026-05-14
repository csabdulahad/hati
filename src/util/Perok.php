<?php

namespace Hati\Util;

use Hati\Trunk;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Perok is a small SMTP mail sender built around PHPMailer.
 *
 * A Perok instance represents one configured sender account. SMTP configuration,
 * sender identity, and optional reply-to address are passed directly through the
 * constructor instead of being read from Hati configuration.
 *
 * The object keeps temporary message state while an email is being prepared, such as
 * body, recipients, CC, BCC, reply-to, attachments, embedded images, and alternative
 * body. That message state is cleared automatically after every send attempt. The
 * clear() method is also public so the caller can explicitly reset the current
 * message before reusing the same Perok instance.
 *
 * This makes the class suitable for creating one instance per request, job, or
 * controlled loop and reusing it to send multiple emails sequentially.
 *
 * Do not share the same mutable Perok instance across concurrent OpenSwoole requests
 * or coroutines. Create a separate Perok instance per request/job, or ensure access
 * is strictly sequential.
 *
 * Example:
 * <code>
 * $perok = new Perok(
 *     host: 'smtp.example.com',
 *     port: 587,
 *     username: "no-reply@example.com",
 *     password: "secret",
 *     fromName: "Mama",
 *     fromEmail: "no-reply@example.com",
 *     replyTo: "support@example.com"
 * );
 *
 * $perok->composeHtml("<h1>Hello</h1>");
 * $perok->send("student@example.com", "Welcome");
 * </code>
 *
 * Perok supports plain text email, HTML email, HTML templates, alternative text body,
 * CC, BCC, file attachments, string attachments, and embedded CID images.
 */
class Perok
{
	
	// PHPMailer instance used to prepare and send the current message.
	private PHPMailer $phpMailer;
	
	/**
	 * Creates a configured SMTP sender.
	 *
	 * @param string $host SMTP server host.
	 * @param int $port SMTP server port.
	 * @param string $username SMTP authentication username.
	 * @param string $password SMTP authentication password.
	 * @param string $fromName Sender display name shown to recipients.
	 * @param string $fromEmail Sender email address.
	 * @param string $replyTo Optional default reply-to email address.
	 *
	 * @throws Trunk If the sender identity cannot be initialized.
	 */
	public function __construct(
		private readonly string $host,
		private readonly int    $port,
		private readonly string $username,
		private readonly string $password,
		private readonly string $fromName,
		private readonly string $fromEmail,
		private readonly string $replyTo = ''
	)
	{
		// Initialize PHPMailer
		$this->phpMailer = new PHPMailer(true);
		$this->phpMailer->CharSet = PHPMailer::CHARSET_UTF8;
		$this->phpMailer->isSMTP();
		$this->phpMailer->SMTPAuth = true;
		$this->phpMailer->SMTPSecure =  PHPMailer::ENCRYPTION_STARTTLS;
		
		// Configure SMTP connection details.
		$this->phpMailer->Host = $this->host;
		$this->phpMailer->Port = $this->port;
		$this->phpMailer->Username = $this->username;
		$this->phpMailer->Password = $this->password;
		
		// Set up sender identity.
		try {
			$this->phpMailer->setFrom($this->fromEmail, $this->fromName);
		} catch (Exception $e) {
			throw new Trunk('Perok encountered error during initialization: ' . $e->getMessage());
		}
	}
	
	/**
	 * Adds one or more Blind Carbon Copy (BCC) recipients to the current message.
	 *
	 * Recipients may be passed as separate string arguments, arrays of strings, or both.
	 *
	 * Example:
	 * <code>
	 * $perok->bcc('a@example.com', 'b@example.com');
	 * $perok->bcc(['a@example.com', 'b@example.com']);
	 * </code>
	 *
	 * @param array|string ...$email Email address, list of email addresses, or mixed variadic values.
	 *
	 * @throws Exception If PHPMailer fails to add a BCC recipient.
	 */
	public function bcc(array|string ...$email): void
	{
		foreach ($email as $e) {
			if (is_array($e)) {
				foreach ($e as $ee) $this->phpMailer->addBCC($ee);
				continue;
			}

			$this->phpMailer->addBCC($e);
		}
	}
	
	/**
	 * Adds one or more Carbon Copy (CC) recipients to the current message.
	 *
	 * Recipients may be passed as separate string arguments, arrays of strings, or both.
	 *
	 * Example:
	 * <code>
	 * $perok->cc('a@example.com', 'b@example.com');
	 * $perok->cc(['a@example.com', 'b@example.com']);
	 * </code>
	 *
	 * @param array|string ...$email Email address, list of email addresses, or mixed variadic values.
	 *
	 * @throws Exception If PHPMailer fails to add a CC recipient.
	 */
	public function cc(array|string ...$email): void
	{
		foreach ($email as $e) {
			if (is_array($e)) {
				foreach ($e as $ee) $this->phpMailer->addCC($ee);
				continue;
			}

			$this->phpMailer->addCC($e);
		}
	}
	
	/**
	 * Sends the prepared message using PHPMailer and clears message-specific state.
	 *
	 * This method is the final sending point used by send() and sendBulk(). The current
	 * subject is applied before sending. Whether sending succeeds or fails, clear() is
	 * called in the finally block to prevent recipients, attachments, body, or headers
	 * from leaking into the next message.
	 *
	 * @param string $subject Email subject.
	 *
	 * @return bool True if PHPMailer reports a successful send; false otherwise.
	 *
	 * @throws Exception If PHPMailer fails while sending.
	 * @throws Trunk If clearing/resetting the sender fails.
	 */
	private function phpMailerSend(string $subject): bool
	{
		$this->phpMailer->Subject = $subject;
		
		try {
			return $this->phpMailer->send();
		} finally {
			$this->clear();
		}
	}
	
	/**
	 * Sets the current message body as plain text.
	 *
	 * This disables HTML mode for the current message and replaces the existing body.
	 *
	 * @param string $msg Plain text email body.
	 */
	public function composeText(string $msg): void
	{
		$this->phpMailer->isHTML(false);
		$this->phpMailer->Body = $msg;
	}
	
	/**
	 * Sets the current message body as HTML.
	 *
	 * This enables HTML mode for the current message and replaces the existing body.
	 *
	 * @param string $msg HTML email body.
	 */
	public function composeHtml(string $msg): void
	{
		$this->phpMailer->isHTML();
		$this->phpMailer->Body = $msg;
	}
	
	/**
	 * Loads an HTML file and uses its contents as the current email body.
	 *
	 * The file must exist and be readable. When loading succeeds, HTML mode is enabled
	 * and PHPMailer's msgHTML() method is used to prepare the body.
	 *
	 * @param string $filePath Path to the HTML file.
	 * @param bool $throwErr Whether to throw a Trunk exception on failure.
	 *
	 * @return bool True if the HTML file was loaded and composed successfully; false otherwise.
	 *
	 * @throws Trunk If loading or composing fails and $throwErr is true.
	 */
	public function composeFromHtml(string $filePath, bool $throwErr = false): bool
	{
		if (!is_file($filePath) || !is_readable($filePath)) {
			if ($throwErr) {
				throw new Trunk('Failed composing HTML page as body: file is not readable.');
			}
			
			return false;
		}
		
		$html = file_get_contents($filePath);
		
		if ($html === false) {
			if ($throwErr) {
				throw new Trunk('Failed composing HTML page as body: could not read file.');
			}
			
			return false;
		}
		
		try {
			$this->phpMailer->isHTML();
			$this->phpMailer->msgHTML($html);
			return true;
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed composing HTML page as body: ' . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Sets the plain text alternative body for an HTML email.
	 *
	 * Email clients may show this text when HTML rendering is unavailable or disabled.
	 *
	 * @param string $msg Plain text alternative body.
	 */
	public function altEmail(string $msg): void
	{
		$this->phpMailer->AltBody = $msg;
	}
	
	/**
	 * Attaches a file to the current message.
	 *
	 * @param string $filePath Path to the file to attach.
	 * @param string $fileName Optional display name for the attachment.
	 * @param bool $throwErr Whether to throw a Trunk exception on failure.
	 *
	 * @throws Trunk If attaching fails and $throwErr is true.
	 */
	public function attachFile(string $filePath, string $fileName = '', bool $throwErr = false): void
	{
		try {
			$this->phpMailer->addAttachment($filePath, $fileName);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed attaching file: ' . $e->getMessage());
		}
	}
	
	/**
	 * Attaches string content as a file to the current message.
	 *
	 * This is useful for generated files such as CSV, JSON, reports, or logs that do
	 * not need to be written to disk before attaching.
	 *
	 * @param string $fileAsStr File contents.
	 * @param string $fileName Display filename, preferably including extension.
	 * @param bool $throwErr Whether to throw a Trunk exception on failure.
	 *
	 * @throws Trunk If attaching fails and $throwErr is true.
	 */
	public function attachStr(string $fileAsStr, string $fileName, bool $throwErr = false): void
	{
		try {
			$this->phpMailer->addStringAttachment($fileAsStr, $fileName);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed to attach string as file: ' . $e->getMessage());
		}
	}
	
	/**
	 * Embeds an image into the current message for use as a CID image in HTML.
	 *
	 * The second argument is used as the CID value. The HTML body can reference the
	 * embedded image using:
	 *
	 * <code>
	 * <img src="cid:logo">
	 * </code>
	 *
	 * @param string $filePath Path to the image file.
	 * @param string $cid CID value used to reference the image from the HTML body.
	 * @param bool $throwErr Whether to throw a Trunk exception on failure.
	 *
	 * @throws Trunk If embedding fails and $throwErr is true.
	 */
	public function embedCIDImage(string $filePath, string $cid, bool $throwErr = false): void
	{
		try {
			$this->phpMailer->addEmbeddedImage($filePath, $cid);
		} catch (Exception $e) {
			if ($throwErr) throw new Trunk('Failed embedding image: ' . $e->getMessage());
		}
	}
	
	/**
	 * Sends the current message to a single recipient.
	 *
	 * The message body, attachments, CC, BCC, embedded images, and alternative body
	 * should be prepared before calling this method. A per-call reply-to address may
	 * be provided. If it is empty, the constructor-level reply-to address is used.
	 *
	 * A per-call from name may also be provided to override the constructor-level
	 * sender display name for this message only. The sender email address remains the
	 * configured fromEmail.
	 *
	 * Message-specific state is cleared after the send attempt.
	 *
	 * @param string $to Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $replyTo Optional reply-to address for this message.
	 * @param string $fromName Optional sender display name override for this message.
	 * @param bool $throwErr Whether to throw a Trunk exception on failure.
	 *
	 * @return bool True if the message was sent successfully; false otherwise.
	 *
	 * @throws Trunk If sending fails and $throwErr is true.
	 */
	public function send(string $to, string $subject, string $replyTo = '', string $fromName = '', bool $throwErr = false): bool
	{
		try {
			if (!empty($fromName)) $this->phpMailer->setFrom($this->fromEmail, $fromName);
			
			// Use per-call reply-to, otherwise fallback to the constructor reply-to.
			$replyTo = empty($replyTo) ? $this->replyTo : $replyTo;
			if (!empty($replyTo)) $this->phpMailer->addReplyTo($replyTo);

			$this->phpMailer->addAddress($to);
			return $this->phpMailerSend($subject);
		} catch (Exception $e) {
			$this->clear();
			
			if ($throwErr) throw new Trunk('Failed sending email: ' . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Sends the current message to multiple recipients using BCC.
	 *
	 * The addresses argument must contain comma-separated email addresses. Empty values
	 * are trimmed and ignored. The same subject, body, attachments, CC, BCC, embedded
	 * images, and alternative body are used for all recipients.
	 *
	 * The constructor-level reply-to address is applied when available. A per-call
	 * from name may be provided to override the constructor-level sender display name
	 * for this message only. The sender email address remains the configured fromEmail.
	 *
	 * Message-specific state is cleared after the send attempt.
	 *
	 * @param string $addresses Comma-separated recipient email addresses.
	 * @param string $subject Email subject.
	 * @param string $fromName Optional sender display name override for this message.
	 * @param bool $throwErr Whether to throw a Trunk exception on failure.
	 *
	 * @return bool True if the message was sent successfully; false otherwise.
	 *
	 * @throws Trunk If no recipient is provided and $throwErr is true.
	 * @throws Trunk If sending fails and $throwErr is true.
	 */
	public function sendBulk(string $addresses, string $subject = '', string $fromName = '', bool $throwErr = false): bool
	{
		$emails = array_filter(array_map('trim', explode(',', $addresses)));
		
		if (empty($emails)) {
			if ($throwErr) throw new Trunk('Failed sending bulk emails: no recipient address provided.');
			return false;
		}
		
		try {
			if (!empty($fromName)) $this->phpMailer->setFrom($this->fromEmail, $fromName);
			if (!empty($this->replyTo)) $this->phpMailer->addReplyTo($this->replyTo);
			
			foreach ($emails as $email) $this->phpMailer->addBCC($email);
			
			return $this->phpMailerSend($subject);
		} catch (Exception $e) {
			$this->clear();
			
			if ($throwErr) throw new Trunk('Failed sending bulk emails: ' . $e->getMessage());
			
			return false;
		}
	}
	
	/**
	 * Clears all message-specific state from the internal PHPMailer instance.
	 *
	 * This removes recipients, reply-to addresses, attachments, embedded images,
	 * custom headers, subject, body, alternative body, error info, and resets the
	 * message back to plain text mode. The configured sender identity is restored.
	 *
	 * This method does not change SMTP configuration, authentication credentials,
	 * fromEmail, fromName, or the constructor-level replyTo value.
	 *
	 * It is called automatically after every send attempt, but may also be called
	 * manually before preparing a new message.
	 *
	 * @return static The current Perok instance.
	 *
	 * @throws Trunk If the sender identity cannot be restored.
	 */
	public function clear(): static
	{
		$this->phpMailer->clearAllRecipients();
		$this->phpMailer->clearReplyTos();
		$this->phpMailer->clearAttachments();
		$this->phpMailer->clearCustomHeaders();
		
		$this->phpMailer->Subject = '';
		$this->phpMailer->Body = '';
		$this->phpMailer->AltBody = '';
		$this->phpMailer->ErrorInfo = '';
		
		$this->phpMailer->isHTML(false);
		
		try {
			$this->phpMailer->setFrom($this->fromEmail, $this->fromName);
		} catch (Exception $e) {
			throw new Trunk('Perok failed resetting sender: ' . $e->getMessage());
		}
		
		return $this;
	}

}