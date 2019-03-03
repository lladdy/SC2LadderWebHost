# DB

Very basic database versioning.

## Setup

Create a new file called `/db/dbconf.php` based upon `dbconf-example.php` and populate it with relevant values.
You can use root login details here.


## Run db.php

### Bootstrap a new database from scratch

`php db.php boostrapdb`

Running boostrapdb will create and configure a database and user, both named after whatever database name is in your `/db/dbconf.php`.


### Upgrading an existing database

`php db.php upgradedb`

Running upgradedb will run all upgrade steps against your configured database.


## Writing an upgrade step

These are the basic steps for writing an upgrade step:

1) Establish what changes need to occur in the database to get from the previous version to your new version.
2) Create a folder for the new database version in `/db/`, using the existing naming convention, and place any required files for the upgrade (such as SQL scripts) in it.
3) In the `SchemaManager` class inside `/db/db.php`, write a new upgrade step function using the existing format and add its call to the end of the upgrade steps within the `upgradeDb()` function.
4) Update the `EXPECTED_DATABASE_VERSION` in `/www/header.php` to your new version.