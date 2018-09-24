# magerun2-password-normalizer
[netz98 Magerun2](https://github.com/netz98/n98-magerun2) Plugin for normalizing all customer-email-addresses and passwords.

## Installation

The preferred way of installing `bitexpert/magerun2-password-normalizer` is through Composer.
Simply add `bitexpert/magerun2-password-normalizer` as a dev dependency:

```
composer.phar require --dev bitexpert/magerun2-list-api-endpoints
```

## Usage

This plugin adds the `dev:customer:normalize-passwords` command to magerun2.

**It is designed to be executed only on development- or test-systems!**

**You should never execute this on a production-system!**

You will not be able to recover the old data, unlees you backed them up.

## Options

### You must provide a password that will be used for every (except exluded) customer

### You can provide an exclude-parameter that will not update the users that match the query.

Example: `--exclude-emails %@bitexpert.%` will result in a query restricted with `WHERE email NOT LIKE '%@bitexpert.%'` thus NOT updating the password and meail-address all bitExpert accounts

### You can provide an email-mask

This command will also change every email-address for the customer (except exluded).
The default is `customer_(ID)@example.com` with `(ID)` being actually replaced by the customer-entity-ID. If you provide a custom email-mask you must include `(ID)`.
Example: `--email-mask foo_(ID)_bar@somefictional.org` will result in a query restricted with `WHERE email NOT LIKE '%@bitexpert.%'` thus NOT updating the password and meail-address all bitExpert accounts
