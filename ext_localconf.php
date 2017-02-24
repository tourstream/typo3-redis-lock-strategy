<?php

defined('TYPO3_MODE') || die();

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Locking\LockFactory;
use Tourstream\RedisLockStrategy\RedisLockStrategy;

/** @var LockFactory $lockFactory */
$lockFactory = GeneralUtility::makeInstance(LockFactory::class);
$lockFactory->addLockingStrategy(RedisLockStrategy::class);
