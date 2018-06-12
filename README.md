# Orange Framework

Extensions to the CodeIgniter Framework

URL:
[https://www.codeigniter.com/]()

Manual:
[https://www.codeigniter.com/user_guide/index.html]()

## Terms
### Catalog
a view variable which contains a array of model records.

### Filters
functions used to "Filter" some type of input. ie. like PHPs trim for example.

### validations
functions used to "Validate" some type of input. Failures are registered with the Errors object

### middleware
functions which can be called based on the url

### Pear
view specific functions. These are called using PHP static syntax

### trigger
events or like Drupal Hooks

### Packages
As the name indicates the packages folder contains HMVC or composer like packages
each independent package is like a mini application folder with a few exceptions


## File Structure

`.env`
Used to storing configuration in the environment separate from code.

This is a PHP file without the .php extension but loaded with a standard php include function.

This file must return a PHP array

It is common practice to not committing your `.env` file to version control.

You can think of them as Drupals settings.php file

`/application`
As the name indicates the Application folder contains all the code of your application that you are building.

`/bin`
Storage for misc shell scripts which pertain to your application

for example special deployment scripts, script to start the PHP built in server

`composer.json`
PHP composer file see: https://getcomposer.org/doc/

`deploy.json`
PHP Deploy Library file see: https://github.com/dmyers2004/deploy


Additional Documents to come.

`/packages`
As the name indicates the packages folder contains HMVC or composer like packages


each independent package is like a mini application folder with a few exceptions
These are usually individual GIT repros
You can think of them as Drupal Modules

`/public`
This is the publicly accessible folder apache "servers" files from.

`/public/index.php`
The `index.php` serves as the front controller/router.


https://www.codeigniter.com/user_guide/overview/appflow.html?highlight=index%20php

`/support`
In order to a project organized, This folder contain things such as database back ups, import files, migrations, SSL keys, etc...

`/var`


This folder much like the Linux equivalent contains things like caches, downloads, emails, logs, sessions, uploads.
This folder is NOT managed in your GIT repo and should be ignored.

`/vendor`


This folder is created and managed by PHP Composer.
This folder is NOT managed in your GIT repo and should be ignored.

## Core Classes

`core/MY_Config.php`


provides extensions to CodeIgniter config such as getting configuration using dot notation with a default, flushing of cached config data, loading configuration if needed from a database, etc

`core/MY_Controller.php`


provides extensions to CodeIgniter Controller for automatically handling if site is down for maintenance. Calling middleware if needed, auto loading libraries, models, helpers for this controller (not needed to much anymore using the new extended `ci()` function) auto loading catalogs. attaching a controller specific model

`core/MY_Input.php`


provides extensions to CodeIgniter Input for grouping PUT and POST into a single function (no need for the dev to switch between them). Advanced auto remapping of request data. Updated wrapper for reading cookies with default.

`core/MY_Loader.php`


provides extensions to CodeIgniter loader to making loading and overloading classes faster (since it uses a array to locate classes and not a file system scan)

`core/MY_Log.php`


provides extensions to CodeIgniter Log to provide support for PSR3 bitwise levels as well as monolog if necessary.

`core/MY_Model.php`


provides extensions to CodeIgniter Model with validation. Not specific to Databases because not all models are modeling database tables.

`core/MY_Output.php`


provides extensions to CodeIgniter Output with json, nocache, wrapper for inputs set cookie since setting cookies are more of a output function and simple function to delete all cookies

`core/MY_Router.php`


provides extensions to CodeIgniter Router to automatically handle controllers in Packages. This also added the Action suffix and HTTP method (Post, Put, Delete, Cli (command line), Get (none)). This uses a advanced caching technique to make this lighting fast (no filesystem scanning etc.)

`core/Orange.php`


provides additional core functions to CodeIgniter.
methods include but are not limited to:

`ci()` a much smarter get_instance()

`site_url()` smarter site_url

`config()` wrapper for configs dot_item

`filter()` wrapper for running a validation filter

`valid()` wrapper for running a validation

`esc()` wrapper for escaping

`e()` wrapper for html special chars

`env()` wrapper to read environmental variables with a default

`view()` most basic wrapper for loading views

`unlock_session()` ajax necessity more english wrapper for PHP session_write_close() function

`console()` simple browser level debugger shows up in the javascript console

`l()` raw logging function

`atomic_file_put_contents()` PHP necessary atomic file writing

`remove_php_file_from_opcache()` APC / OPcache friendly Remove from cache function

`convert_to_real()` and `convert_to_string()` Converting to and from values

`simplify_array()` Collapse a array with multiple values into a single key=>value pair

`cache()` Wrapper function to sue the currently loaded cache library in a closure fashion

`cache_ttl()` Get the current Cache Time to Live with optional "window" support to negate a cache stamped

`delete_cache_by_tags()` Delete cache records based on dot notation tags" to allow deleting cache records based on multiple values

## Libraries

`libraries/Auth.php` Provides the authorization for users. Functions include `login()`, `logout()`, `refresh_userdata()`.

`libraries/Cache_export.php` Provides the library to cache to the file system. no need to focus on in the short term

`libraries/Cache_request.php` Provides the library to create a cache for the current request (in stored in PHP memory) no need to focus on in the short term

`libraries/Errors.php` Provides a unified library to register errors for further processing

`libraries/Event.php` Provides the library to provides events in your app. Methods include `register()` & `trigger()` as well as some simple supporting methods (`count`, `has`)

`libraries/Filter_base.php` Provides the abstract base class for all filters and extends validate_base class (just a basic placeholder)
no need to focus on in the short term

`libraries/filters/` Folder to contain filters all filters start with Filter_*.php

`libraries/Middleware_base.php` Provides the abstract base class for all middleware (just a basic placeholder) no need to focus on in the short term

`libraries/Orange_autoload_files.php` Provides generates the autoload cache file of controllers, libraries, models, classes, etc
no need to focus on in the short term

`libraries/Page.php` Provides the heavy lifting of building HTML Pages.
methods include but are not limited to:

* `title()` Set the pages title if different than the default

* `meta()` Add additional meta data

* `body_class()` Add a class to the body

* `render()` Render the page and send its output to the output class

* `view()` Basic MVC view function https://www.codeigniter.com/user_guide/libraries/loader.html#CI_Loader::view

* `data()` Attach data to a page from any where in the application

* `icon()` Change the default icon

* `css()` Add additional links to page

* `link_html()` Create and return <link> html

* `style()` Add style to page

* `js()` Add additional javascript to a page

* `script_html()` Create and return <script> html

* `js_variable()` & `js_variables()` Add a javascript variable to the page

* `script()` Add additional scripts to a page

* `domready()` Add javascript to the domready section

* `ary2element()` Convert PHP array to HTML element

* `convert2attributes()` Convert PHP array to HTML attributes

* `set_priority()` Set priority to added elements

* `reset_priority()` Reset element priority to default 50

`libraries/Pear_plugin.php` base class for pear plugins. Really only provides a `_convert2attributes()` for all children objects

`libraries/Pear.php` Provides the HTML View Pear plugin functions (to be used in view only)
each plugin has really only 2 methods:
its class constructor `__construct` and `render()`
the constructor is called when the plugin is loaded for the first time
if a plugin just adds for example css or js to a page you can include it with the plugins() & plugin() methods
if your plugin is used in a view to do something you simple call `pear::foobar($foo,23)` which will automatically load the foobar plugin  (which of course called the constructor if its present) and then sends $foo and 23 to the render method. the render method can then return something which can be echoed. Plugins should echo directly but instead return a value which can be echoed. `<?=pear::foobar($foo,23) ?>`

Built in Pear methods include but are not limited to:
All of the CodeIgniter Helpers functions for html, form, date, inflector, language, number, text
in additional you can call the form helper functions without the form_ prefix
so `pear::form_input()` and `pear::input()` are the same thing

### Others added include:
* `section` start a page variable section with the supplied name
* `parent` append to prepend to the current page variable section without this you will overwrite a page section if it already contains something
* `end` end the current page variable section
* `extends` a view can only extend 1 other view (normally the base template)
* `includes` include another template into scope
* `is_extending` returns the currently template we are extending if any
* `plugins() & plugin` load plugins without actually calling the render function

`libraries/User.php`
Static wrapper for the orange user object.
no need to focus on in the short term since its just a 7 line wrapper

`libraries/Validate_base.php`
provides the abstract base class for all validations and filters
it provides the basic method for length, trim, human, human_plus, strip, is_bol to all its children classes

`libraries/Validate.php`
The heavy lifter for input validation. Methods include but are not limited to:

* `clear` clear all current errors
* `attach` attach validation as a closure
* `die_on_fail` die on first validation fail.
* `redirect_on_fail` redirect to a different URL on fail.
* `json_on_fail` output errors as json on fail.
* `success` return boolean true or false
* `variable` easy way to run a validation on a variable
* `request` easy way to run a validation on a request value (via a key)
* `run` auto detects a single or multiple validations
* `single` run a single validation
* `multiple` run multiple validations

`libraries/validations/`
folder to contain validations. all validations start with `Validate_*.php`

`libraries/Wallet.php`
wallet provides additional features to CodeIgniter Flash Messaging as well as some other session based functions.
methods include but are not limited to:
* `pocket` a more generic version of cache_requests features its both a getter and setter
* `snapdata` set session data and leave it there for up to 1 hour or until it read
* `get_snapdata` get session data and remove
* `keep_snapdata` get session data and do not remove
* `msg` set a flash msg with additional features such as color & redirect
This uses custom CSS & Javascript to show OS X like alerts in a bootstrap
https://getbootstrap.com/docs/3.3/components/#alerts
* `stash` stores request array
* `unstash` retrieves and restores request array

## Models
`models/Database_model.php`
This provides the reusable methods for actual database table models
methods include but are not limited to:
* `get_tablename` return models tablename
* `get_primary_key` return models primary key
* `get_soft_delete` is this table using soft deletes?
* `with_deleted` select with deleted
* `only_deleted`  select only deleted
* `as_array` return as a array not an object
* `column` select a single column
* `on_empty_return` if nothing found return
* `get` select single record
* `get_by` select single record with filter
* `get_many` select multiple records
* `get_many_by` select multiple records with filter
* `insert` insert record
* `update` update record
* `update_by` update record with filter
* `delete` delete record
* `delete_by` delete record with filter
* `delete_cache_by_tags` deleted cache by tags
* `catalog` select records for a data array
* `exists` test if record exists with filter
* `count()` - count records
* `count_by()` - count records with filter
* `index()` select for index table view

### Included Models
`models/O_permission_model.php` Model for permissions table
`models/O_role_model.php` Model for roles table
`models/O_setting_model.php` Model for settings table
`models/O_user_model.php` Model for users table

### Model Entities
`models/Model_entity.php`
provides the abstract base class for all model entities (just a basic placeholder)
provides a `save()` method to have a entity save itself

### Included Models
`models/entities/O_permission_entity.php`
`models/entities/O_role_entity.php`
`models/entities/O_user_entity.php`

### Model Traits
Folder of traits models can inherit
