**Orange Framework**

URL:

<https://www.codeigniter.com/>

Manual:

<https://www.codeigniter.com/user_guide/index.html>

**Terms**

Catalog

a view variable which contains a array of model records.

Filters

functions used to "Filter" some type of input. ie. like PHPs trim for example.

validations

functions used to "validate" some type of input. Failures are registered with the Errors object

middleware

functions which can be called based on the url

Pear

view specific functions. These are called using PHP static syntax

trigger

triggers a event

Packages

As the name indicates the packages folder contains HMVC or composer like packages

each independent package is like a mini application folder with a few exceptions

**File Structure**

.env

Used to storing configuration in the environment separate from code.

This is a PHP file without the .php extension but loaded with a standard php include function.

This file must return a PHP array 

It is common practice to not committing your .env file to version control.


/application

As the name indicates the Application folder contains all the code of your application that you are building.

/bin

Storage for misc shell scripts which pertain to your application

for example special deployment scripts, script to start the PHP built in server

composer.json

PHP composer file see: <https://getcomposer.org/doc/>

deploy.json

PHP Deploy Library file see: <https://github.com/dmyers2004/deploy>

Additional Documents to come.

/packages

As the name indicates the packages folder contains HMVC or composer like packages

each independent package is like a mini application folder with a few exceptions

These are usually individual GIT repository

/public

This is the publicly accessible folder apache "servers" files from.

/public/index.php

The index.php serves as the front controller or router.

<https://www.codeigniter.com/user_guide/overview/appflow.html?highlight=index%20php>

/support

In order to a project organized, This folder contain things such as database back ups, import files, migrations, SSL keys, etc...

/var

This folder much like the Linux equivalent contains things like caches, downloads, emails, logs, sessions, uploads.

This folder is NOT managed in your GIT repository and should be ignored.

/vendor

This folder is created and managed by PHP Composer.

This folder is NOT managed in your GIT repository and should be ignored.

**Core Classes**

**core/MY_Config.php**

provides extensions to CodeIgniter config such as getting configuration using dot notation with a default, flushing of cached config data, loading configuration if needed from a database, etc...

**core/MY_Controller.php**

provides extensions to CodeIgniter Controller for automatically handling if site is down for maintenance. Calling middleware if needed, auto loading libraries, models, helpers for this controller (not needed to much anymore using the new extended ci() function) auto loading "catalogs". attaching a "controller" specific model

**core/MY_Input.php**

provides extensions to CodeIgniter Input for grouping PUT and POST into a single function (no need for the dev to switch between them). Advanced auto "remapping" of request data. Updated wrapper for reading cookies with default.

**core/MY_Loader.php**

provides extensions to CodeIgniter loader to making loading and "overloading" classes faster (since it uses a array to locate classes and not a file system "scan")

**core/MY_Log.php**

provides extensions to CodeIgniter Log to provide support for PSR3 bitwise levels as well as monolog if necessary.

**core/MY_Model.php**

provides extensions to CodeIgniter Model with validation. Not specific to Databases because not all models are modeling database tables.

**core/MY_Output.php**

provides extensions to CodeIgniter Output with json, nocache, wrapper for inputs "set cookie" since setting cookies are more of a output function and simple function to delete all cookies

**core/MY_Router.php**

provides extensions to CodeIgniter Router to automatically handle controllers in Packages. This also added the "Action" suffix and HTTP method (Post, Put, Delete, Cli (command line), Get (none)). This uses a advanced caching technique to make this lighting fast (no filesystem scanning etc.)...

**core/Orange.php**

Provides additional functions to CodeIgniter.

methods include but are not limited to:

**ci(class)**

a much smarter get_instance()

To get a reference to the CodeIgniter "Super Object" you simple use get_instance()

<https://www.codeigniter.com/user_guide/general/ancillary_classes.html?highlight=get_instance#get_instance>

But I've made it smarter by making it a little more like a dependency injection contain. 

You can autoload a library or model simply by entering it's name in the function ie.

**ci('email')->send();**

this will auto load the email library if it's not already loaded and then call the send method. 

Then you don't need to call

**ci()->load->library('email');**

**ci()->email->send();**

This also becomes a dependency injection contain because the 'email' library can be something other than a class named 'email' (the default CI way of loading classes)

This "remapping" is handled by the remap entry in /config/autoload.php

**$autoload['remap'] = [**

**'auth'=>'ldap_auth',**

**'o_user_entity'=>'O_ldap_user_entity',**

**];**

in this example ldap_auth is loaded instead of auth when ci('auth') is called. or a mock_auth for testing etc...

**site_url(uri,protocol)**

smarter site_url

This does everything the CodeIgniter site_url() does

<https://www.codeigniter.com/user_guide/helpers/url_helper.html?highlight=site_url#site_url>

except this version of the function added a simple find and replace for {} tags. These tags are loaded from /config/paths.php. This then makes it easier to have any reused path in paths.php and by using site_url() load them.

**$path = site_url('/{www theme}/assets/css')**

**config(setting,default)**

wrapper for config's dot_item calls

**ci('config')->dot_item()**

This makes loaded configuration values a lot easier and it's fully cached. 

**config('auth.login h2')**

will return the value for /config/auto.php array key 'login h2'.

Just like

**$html = ci('config')->dot_item('auth.login h2','Login');**

you can also provided a default value. 

**$html = config('auth.login h2','Login');**

**filter(rules,field)**

wrapper for running a validation filter calls

**ci('validate')->single($rules,$field);**

**valid(rule,field)**

wrapper for running a validation calls

**ci('validate')->single($rule,$field);**

and returns value of

**!ci('errors')->has();**

**esc(string)**

escape " with \"

**e(html)**

performs a html encoding of special characters

**env(key,default)**

wrapper to read environmental variables with a default loads values from the .env file. This should be used in the /config/*.php files. very rarely should you call it in actual code. exceptions might be to load the server environment or debug mode.

**view(view,data)**

most basic wrapper for loading views

<https://www.codeigniter.com/user_guide/general/views.html?highlight=view#adding-dynamic-data-to-the-view>

***NOTE*** this ALWAYS returns the rendered view. This does not echo anything.

**unlock_session()**

more english wrapper for PHP session_write_close() function

**console(var,type)**

simple browser level debugger shows up in the javascript console wrapper for

**echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';**

**l(...)**

"raw" logging function 

Simple Logging function for debugging purposes

***ALWAYS*** writes to LOGPATH.'/orange_debug.log'

**atomic_file_put_contents(file path,content)**

PHP necessary atomic file writing

Writes to a file without the needed to worry about another process loading it write it's still being written to.

**remove_php_file_from_opcache(full path)**

APC / OPcache friendly Remove from cache function

removes a file from opcache or apache without worrying about if it's actually loaded

**convert_to_real(value) and convert_to_string(value)**

Converting to and from values

Try to convert a value to it's real type this is nice for pulling string from a database such as configuration values stored in string format

**simplify_array(array,key,value)**

collapse a array with multiple values into a single key=>value pair

this will collapse a array with multiple values into a single key=>value pair

**cache(key,closure,ttl)**

Wrapper function to sue the currently loaded cache library in a closure fashion

**cache_ttl(use window)**

Get the current Cache Time to Live with optional "window" support to negate a cache stamped

**delete_cache_by_tags(tags)**

Delete cache records based on dot notation "tags" therefore if you have a cache keys of:

acl.users.database.table

acl.groups.database.table

food.database.table

colors.file

colors.table

colors_status.table

You can use different tags

**delete_cache_by_tags('acl')**

**delete_cache_by_tags('acl','user','roles')**

**delete_cache_by_tags('acl.user.roles')**

**delete_cache_by_tags(['acl','user','roles'])**

if the cache key has one or more matching tag delete the record**libraries/Auth.php**

provides the authorization for users. Functions include

**login(user identifier, password)**

**logout()**

**refresh_userdata(user identifier, save session)**

**libraries/Cache_export.php**

provides the library to cache to the file system

**libraries/Cache_request.php**

provides the library to create a cache for the *current* request (in stored in PHP memory)

**libraries/Errors.php**

provides a unified library to register errors for further processing

**libraries/Event.php**

provides the library to provides events in your app

methods include 

**register(name, closure, priority)**

**trigger(name, a#...)**

**count(name)**

**has(name)**

**libraries/Filter_base.php**

provides the abstract base class for all filters and extends validate_base class (just a basic placeholder)

no need to focus on in the short term

**libraries/filters/**

folder to contain filters

all filters start with Filter_*.php

**libraries/Middleware_base.php**

provides the abstract base class for all middleware (just a basic placeholder)

**libraries/Orange_autoload_files.php**

provides generates the autoload cache file of controllers, libraries, models, classes, etc...

**libraries/Page.php**

provides the heavy lifting of building HTML Pages.

methods include but are not limited to:

**title(title)**

set the pages title if different than the default

**meta(attribute, name, content, priority)**

add additional meta data

**body_class(class, priority)**

add a class to the body

**render(view, data)**

render the page and send it's output to the output class

**view(view file, data, return)**

basic MVC view function

<https://www.codeigniter.com/user_guide/libraries/loader.html#CI_Loader::view>

**data(name, value)**

attach data to a page from any where in the application

**icon(image path)**

change the default icon

**css(file, priority)**

add additional links to page

**link_html(file)**

create and return <link> html

**style(style, priority)**

add style to page

**js(file, priority)**

add additional javascript to a page

**script_html(file)**

create and return <script> html

**js_variable(key, value, priority, raw) & js_variables(array)**

add a javascript variable to the page

**script(script, priority)**

add additional scripts to a page

**domready(script, priority)**

add javascript to the domready section

**ary2element(element, attributes, wrapper)**

convert PHP array to HTML element

**convert2attributes(attributes, prefix, strip_empty)**

convert PHP array to HTML attributes

**set_priority(priority)**

set priority to added elements

**reset_priority()**

reset element priority to default 50

**libraries/Pear_plugin.php**

base class for pear plugins. Really only provides a _convert2attributes() for all children objects

**libraries/Pear.php**

provides the HTML View Pear "plugin" functions (to be used in view only)

each plugin has really only 2 methods:

it's class constructor __construct and render()

the constructor is called when the plugin is loaded for the first time

if a plugin just adds for example css or js to a page you can include it with the plugins() & plugin() methods

if your plugin is used in a view to "do" something you simple call pear::foobar($foo,23) which will automatically load the "foobar" plugin (which of course called the constructor if it's present) and then sends $foo and 23 to the render method. the render method can then return something which can be echoed. Plugins should "echo" directly but instead return a value which can be echoed. <?=pear::foobar($foo,23) ?>

Built in Pear methods include but are not limited to:

All of the CodeIgniter Helpers functions for html, form, date, inflector, language, number, text

in additional you can call the form helper functions without the form_ prefix

so pear::form_input() and pear::input() are the same thing

Others added include

**section(name,value)**

start a page variable section with the supplied name

**parent(name)**

append to prepend to the current page variable section without this you will overwrite a page section if it already contains something

**end()**

end the current page variable section

**extends(name)**

a view can only extend 1 other view (normally the "base" template)

**includes(view, data, name)**

include another template into scope

**is_extending()**

returns the currently template we are extending if any

**plugins(name, priority) & plugin(name, priority)**

load plugins without actually calling the render function

**libraries/User.php**

Static wrapper for the orange user object.

no need to focus on in the short term since it's just a 7 line wrapper

**libraries/Validate_base.php**

provides the abstract base class for all validations and filters

it provides the basic method for length, trim, human, human_plus, strip, is_bol to all it's children classes

**libraries/Validate.php**

The heavy lifter for input validation

methods include but are not limited to:

**clear()**

clear all current errors

**attach(name, closure)**

attach validation as a closure

**die_on_fail(view)**

die on first validation fail.

**redirect_on_fail(url)**

redirect to a different URL on fail.

**json_on_fail()**

output errors as json on fail.

**success()**

return boolean true or false

**variable(rules, field, human)**

easy way to run a validation on a variable

**request(rules, key, human)**

easy way to run a validation on a request value (via a key)

**run(rules, fields, human)**

auto detects a single or multiple validations

**single(rules, fields, human)**

run a single validation

**multiple(rules, fields)**

run multiple validations

**libraries/validations/**

folder to contain validations

all validations start with Validate_*.php

**libraries/Wallet.php**

wallet provides additional features to CodeIgniter Flash Messaging as well as some other session based functions.

methods include but are not limited to:

**pocket(name, value)**

a more generic version of cache_request's features it's both a getter and setter

**snapdata(newdata, newval)**

set session data and leave it there for up to 1 hour or until it read

**get_snapdata(key)**

get session data and remove

**keep_snapdata(key)**

get session data and do not remove

**msg(msg, type, redirect)**

set a flash msg with additional features such as color & redirect

This uses custom CSS & Javascript to show OS X like "alerts" in a bootstrap

<https://getbootstrap.com/docs/3.3/components/#alerts>

**stash()**

stores request array

**unstash()**

retrieves and restores request array**models/Database_model.php**

This provides the reusable methods for actual database table models

methods include but are not limited to:

**get_tablename()**

return models tablename

**get_primary_key()**

return models primary key

**get_soft_delete()**

is this table using soft deletes?

**with_deleted()**

select with deleted

**only_deleted()**

select only deleted

**as_array()**

return as a array not an object

**column(name)**

select a single column

**on_empty_return(return)**

if nothing found return...

**get(primary value)**

select single record

**get_by(where)**

select single record with filter

**get_many()**

select multiple records

**get_many_by(where)**

select multiple records with filter

**insert(data)**

insert record

**update(data)**

update record

**update_by(data, where)**

update record with filter

**delete(arg)**

delete record

**delete_by(where)**

delete record with filter

**delete_cache_by_tags()**

deleted cache by tags

**catalog(array key, select columns, where, order by, cache key, with deleted)**

select records for a data array

**exists(arg)**

test if record exists with filter

**count()**

count records

**count_by(where)**

count records with filter

**index(order by, limit, where, select)**

select for "index" table view



**models/entities/**

folder of model record entities

Entities include

**O_permission_entity.php**

**O_role_entity.php**

**O_user_entity.php**

**models/Model_entity.php**

provides the abstract base class for all model entities (just a basic placeholder)

provides a `save()` method to have a entity save itself

**models/O_permission_model.php**

Model for permissions table

**models/O_role_model.php**

Model for roles table

**models/O_setting_model.php**

Model for settings table

**models/O_user_model.php**

Model for users table

**models/traits/**

folder of traits models can inherit
