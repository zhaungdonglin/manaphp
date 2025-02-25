<?php
namespace ManaPHP;

abstract class Mailer extends Component implements MailerInterface
{
    /**
     * @var string
     */
    protected $_log;

    /**
     * @var string
     */
    protected $_from;

    /**
     * @var string
     */
    protected $_to;

    /**
     * @return \ManaPHP\Mailer\Message
     */
    public function compose()
    {
        $message = $this->_di->get('ManaPHP\Mailer\Message');

        $message->setMailer($this);

        if ($this->_from) {
            $message->setFrom($this->_from);
        }
        if ($this->_to) {
            $message->setFrom($this->_to);
        }

        return $message;
    }

    /**
     * @param \ManaPHP\Mailer\Message $message
     * @param array                   $failedRecipients
     *
     * @return int
     */
    abstract protected function _send($message, &$failedRecipients = null);

    /**
     * @param \ManaPHP\Mailer\Message $message
     * @param array                   $failedRecipients
     *
     * @return int
     */
    public function send($message, &$failedRecipients = null)
    {
        if ($this->_log) {
            $this->filesystem->fileAppend($this->_log, json_stringify($message) . PHP_EOL);
        }

        $failedRecipients = [];

        $message->setMailer($this);
        $this->eventsManager->fireEvent('mailer:sending', $this, ['message' => $message]);
        $r = $this->_send($message, $failedRecipients);
        $this->eventsManager->fireEvent('mailer:sent', $this, ['message' => $message, 'failedRecipients' => $failedRecipients]);

        return $r;
    }
}