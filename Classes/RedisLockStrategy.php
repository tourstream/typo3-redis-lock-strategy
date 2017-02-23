<?php

namespace Tourstream\RedisLockStrategy;

use TYPO3\CMS\Core\Locking\Exception;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;

/**
 * @author Alexander Miehe <alexander.miehe@tourstream.eu>
 */
class RedisLockStrategy implements LockingStrategyInterface
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @inheritdoc
     */
    public function __construct($subject)
    {
        $config = null;

        if (\array_key_exists('redis_lock', $GLOBALS['TYPO3_CONF_VARS']['SYS'])) {
            $config = $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'];
        }

        if (!\is_array($config)) {
            throw new Exception('no configuration for redis lock strategy found');
        }

        if (!\array_key_exists('host', $config)) {
            throw new Exception('no host for redis lock strategy found');
        }
        $port = 6379;

        if (\array_key_exists('port', $config)) {
            $port = (int)$config['port'];
        }

        if (!\array_key_exists('database', $config)) {
            throw new Exception('no database for redis lock strategy found');
        }

        $this->ttl = 360;
        if (\array_key_exists('ttl', $config)) {
            $this->ttl = (int)$config['ttl'];
        }

        $this->subject = $subject;
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $port);

        if (\array_key_exists('auth', $config)) {
            $this->redis->auth($config['auth']);
        }

        $this->redis->select((int)$config['database']);
    }

    /**
     * @inheritdoc
     */
    public static function getCapabilities()
    {
        return self::LOCK_CAPABILITY_EXCLUSIVE | self::LOCK_CAPABILITY_NOBLOCK;
    }

    /**
     * @inheritdoc
     */
    public static function getPriority()
    {
        return 100;
    }

    /**
     * @inheritdoc
     */
    public function acquire($mode = self::LOCK_CAPABILITY_EXCLUSIVE)
    {
        if ($this->isAcquired()) {
            return true;
        }

        return $this->redis->set($this->subject, uniqid(), $this->ttl);
    }

    /**
     * @inheritdoc
     */
    public function isAcquired()
    {
        return $this->redis->exists($this->subject);
    }

    /**
     * @inheritdoc
     */
    public function destroy()
    {
        $this->release();
    }

    /**
     * @inheritdoc
     */
    public function release()
    {
        if (!$this->isAcquired()) {
            return true;
        }

        return $this->redis->del($this->subject) === 1;
    }

}