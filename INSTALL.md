Installing Ratatöskr
====================

Step 0: Requirements
--------------------

* Apache Webserver with PHP 5.3
* These PHP modules (usually installed): gd, hash, session, pdo
* A MySQL server.

Step 1: Get additional libraries
--------------------------------

You need these libraries to run Ratatöskr (it is probably already bundled with these):

1. STE Template Engine (STE)
   
   Place "ste.php" directly into this directory.
   
   STE can be found here: <https://github.com/silvasur/ste>

2. PHP Markdown
   
   Place "markdown.php" from the archive directly into this directory.
   
   PHP Markdown can be found here: <http://michelf.com/projects/php-markdown/>

3. kses
   
   Place "kses.php" from the archive directly into this directory.
   
   kses can be found at <http://sourceforge.net/projects/kses/>

4. jQuery
   
   Place jquery.min.js into this folder.
   
   jQuery can be found at <http://jquery.com>

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
