[![github tag][github-tag-image]][github-tag-url]
[![Packagist version][packagist-version-image]][packagist-version-url]
[![Scrutinizer Code Quality][scrutinizer-image]][scrutinizer-url]
[![Travis-CI][travis-image]][travis-url]
[![License][license-image]][license-url]

***

# TYPO3 Extension: redis lock strategy

The extension adds a redis lock strategy with priority 100. So the redis lock will be used instead of file base locking,
especially useful in cluster with nfs.


## Features

* Redis Lock

## Installation

The recommended way to install the extension is by using [Composer][composer-url]. In your Composer based TYPO3 project root, just do

	composer require tourstream/typo3-redis-lock-strategy 

This extension uses the pecl extension [redis][redis-pecl-url].

## Usage

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => 'localhost',
            'port'     => 6379,       // optional, default 6379
            'database' => 0,          // optional, default 0
            'ttl'      => '60',       // optional, default 60
            'auth'     => 'secret'    // optional, for secured redis db's
        ];

***

[github-tag-image]: https://img.shields.io/github/tag/tourstream/typo3-redis-lock-strategy.svg?style=flat-square
[github-tag-url]: https://github.com/tourstream/typo3-redis-lock-strategy

[packagist-version-image]: https://img.shields.io/packagist/v/tourstream/typo3-redis-lock-strategy.svg?style=flat-square
[packagist-version-url]: https://packagist.org/packages/tourstream/typo3-redis-lock-strategy

[scrutinizer-image]: https://scrutinizer-ci.com/g/tourstream/typo3-redis-lock-strategy/badges/quality-score.png?b=master
[scrutinizer-url]: https://scrutinizer-ci.com/g/tourstream/typo3-redis-lock-strategy/?branch=master

[travis-image]: https://travis-ci.org/tourstream/typo3-redis-lock-strategy.svg?branch=master
[travis-url]: https://travis-ci.org/tourstream/typo3-redis-lock-strategy

[license-image]: https://img.shields.io/github/license/tourstream/typo3-redis-lock-strategy.svg?style=flat-square
[license-url]: https://github.com/tourstream/typo3-redis-lock-strategy/blob/master/LICENSE

[composer-url]: https://getcomposer.org

[redis-pecl-url]: https://pecl.php.net/package/redis
