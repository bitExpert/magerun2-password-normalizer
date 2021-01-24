# magerun2-password-normalizer

[netz98 Magerun2](https://github.com/netz98/n98-magerun2) Plugin for changing the passwords and email-addresses for customer-accounts in bulk.

[![Build Status](https://github.com/bitExpert/magerun2-password-normalizer/workflows/ci/badge.svg?branch=master)](https://github.com/bitExpert/magerun2-password-normalizer)
[![Coverage Status](https://coveralls.io/repos/github/bitExpert/magerun2-password-normalizer/badge.svg?branch=master)](https://coveralls.io/github/bitExpert/magerun2-password-normalizer?branch=master)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/bitExpert/magerun2-password-normalizer/master)](https://infection.github.io)

## Installation

The preferred way of installing `bitexpert/magerun2-password-normalizer` is through Composer.
Simply add `bitexpert/magerun2-password-normalizer` as a dev dependency:

```
composer.phar require --dev bitexpert/magerun2-password-normalizer
```

### Local installation

If you do not want to add the command to one specific project only, you can install the plugin globally by placing the
code in the `~/.n98-magerun2/modules` directory. If the folder does not already exist in your setup, create the folder
by running the following command:

```
mkdir -p  ~/.n98-magerun2/modules
```

The next thing to do is to clone the repository in a subdirectory of `~/.n98-magerun2/modules`:

```
git clone git@github.com:bitExpert/magerun2-password-normalizer.git ~/.n98-magerun2/modules/magerun2-password-normalizer
```

## Usage

This plugin adds the `dev:customer:normalize-passwords` command to magerun2.

**It is designed to be executed only on development- or test-systems!**

You must add --force when you're not in "developer" mode

**You should never execute this on a production-system!**

You will not be able to recover the old data, unlees you backed them up.

## Options

### You must provide a password that will be used for every (except exluded) customer

### You can provide an exclude-parameter that will not update the users that match the query.

Example: `--exclude-emails %@bitexpert.%` will result in a query restricted with `WHERE email NOT LIKE '%@bitexpert.%'` thus NOT updating the password and email-address all bitExpert accounts.
If you want to exclude multiple "conditions" you can provide them ; separated `--exclude-emails %@bitexpert.%;%@gmail%`

### You can provide an email-mask

This command will also change every email-address for the customer (except exluded).
The default is `customer_(ID)@example.com` with `(ID)` being actually replaced by the customer-entity-ID. If you provide a custom email-mask you must include `(ID)`.
Example: `--email-mask foo_(ID)_bar@somefictional.org` will result in a query restricted with `WHERE email NOT LIKE '%@bitexpert.%'` thus NOT updating the password and email-address all bitExpert accounts

## Contribute

Please feel free to fork and extend existing or add new features and send
a pull request with your changes! To establish a consistent code quality,
please provide unit tests for all your changes and adapt the documentation.

## Want To Contribute?

If you feel that you have something to share, then we’d love to have you.
Check out [the contributing guide](CONTRIBUTING.md) to find out how, as
well as what we expect from you.

## License

This plugin is released under the Apache 2.0 license.
