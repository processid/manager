## Unit tests for Manager v3

# Required
in order to launch unit test you lust add  phpunit thought composer and install it.

# Configuration
First you must add a configuration for the database in the config directory (manager/unit/config) .
The factory must be : **'test'**

Nota : **test will add new table**, be sure user provided has the **right to create tables**.

Exemple : "\processid\manager\unit\config\DbConfiguration.json"

````json
{
  "test": {
    "type": "mysql",
    "host": "127.0.0.1",
    "user": "your user",
    "pass": "your password",
    "database": "your data base",
    "key_aes256": "abcdef=",
    "key_hash512": "abcdef==",
    "method": "aes-256-cbc"
  }
}
````

# Run
- You can run the test thought you IDE.
- You can also run then directly from command line (ssh).
  - Open a console
  - Go into this directory
  - Run the command ' sh run.sh'

The test will work on new, specific data. 