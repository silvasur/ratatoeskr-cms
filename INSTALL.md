Installing Ratatöskr
====================

Step 0: Requirements
--------------------

* Apache Webserver with PHP >= 7.3
* These PHP modules (usually installed): gd, hash, session, pdo
* A MySQL (or MariaDB) server.

Step 1: Get required packages using composer
--------------------------------------------

*(If you donloaded a pre-built package, you can skip this step)*

Some required packages are managed by [composer](https://www.getcomposer.org). If you don't have it installed, go and install it.

After that, run `composer install` in the root directory of this package.

Step 2: Copy files to your Webspace
-----------------------------------

Copy Ratatöskr to your webspace (usually using FTP or SFTP).

Step 3: Use the setup wizard
----------------------------

1. Open your favourite Web browser and surf to `setup.php` of your Ratatöskr installation.

2. * If the wizard is complaining about some unmet requirements, your server is probably not capable of running Ratatöskr. Sorry :-(.
   * If the wizard is complaining about missing directories, create them.
   * If the wizard is complaining about missing files, check, if you uploaded everything.
   * If the wizard is complaining about missing writing permissions, give the Webserver writing permissions to these directories.

3. Choose your language.

4. Enter the MySQL connection details and the desired username and password for the admin account.

5. Copy the text from the textbox and replace the contents of `/ratatoeskr/config.php` with it.

Step 4: Delete the setup wizard
-------------------------------

Delete the file `setup.php`.
