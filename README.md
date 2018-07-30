```
Orange Framework

URL:
https://www.codeigniter.com/

Manual:
https://www.codeigniter.com/user_guide/index.html

Terms
Catalog
a view variable which contains a array of model records.

Filters
functions used to “Filter” some type of input. ie. like PHPs trim for example.

Validations
functions used to “validate” some type of input. Failures are registered with the Errors object 

Middleware
functions which can be called based on the url 

Pear
view specific functions. These are called using PHP static syntax

Trigger
Event package for your app and domain

Packages
As the name indicates the packages folder contains HMVC or composer like packages
each independent package is like a mini application folder with a few exceptions


File Structure

.env
Used to storing configuration in the environment separate from code.
http://php.net/manual/en/configuration.file.php
http://php.net/manual/en/function.parse-ini-file.php
It is common practice to not committing your .env file to version control.

/application
As the name indicates the Application folder contains the application specific code.
When using CodeIgniter / Orange thou you should put as much code in packages as possible this makes code reuse a lot easier.	

/bin
Storage for misc shell scripts which pertain to your application
for example special deployment scripts, a script to start the PHP built in server

composer.json
PHP composer file see
https://getcomposer.org/doc/

deploy.json
PHP Deploy file see
https://github.com/dmyers2004/deploy

/packages
As the name indicates the packages folder contains HMVC or composer like packages
each independent package is like a mini application folder with a few exceptions
These are usually individual GIT repositories to simplify package reuse.

/public
This is the publicly accessible folder apache “servers” files from.

/public/index.php
The index.php serves as the front controller or router.
https://www.codeigniter.com/user_guide/overview/appflow.html?highlight=index%20php

/support
In order to keep a project organized, This folder contain things such as database backups, import files, migrations, SSL keys, etc…

/var
This folder much like the Linux equivalent contains things like caches, downloads, emails, logs, sessions, uploads.
This folder should NOT be managed in your GIT repository and should be ignored.

/vendor
This folder is created and managed by PHP Composer.
This folder is NOT managed in your GIT repositories and should be ignored.

Core Classes

core/MY_Config.php
provides extensions to CodeIgniter config such as getting configuration using dot notation with a default, flushing of cached config data, loading configuration if needed from a database, etc…

core/MY_Controller.php
provides extensions to CodeIgniter Controller for automatically handling if site is down for maintenance. Calling middleware if needed, auto loading libraries, models, helpers for this controller (not needed to much anymore using the new extended ci() function) auto loading “catalogs”. attaching a “controller” specific model

core/MY_Input.php
provides extensions to CodeIgniter Input for grouping PUT and POST into a single function (no need for the dev to switch between them). Advanced auto “remapping” of request data. Updated wrapper for reading cookies with default.

core/MY_Loader.php
provides extensions to CodeIgniter loader to making loading and “overloading” classes faster (since it uses a array to locate classes and not a file system “scan”)

core/MY_Log.php
provides extensions to CodeIgniter Log to provide support for PSR3 bitwise levels as well as monolog if necessary.

core/MY_Model.php
provides extensions to CodeIgniter Model with validation. Not specific to Databases because not all models are modeling database tables.

core/MY_Output.php
provides extensions to CodeIgniter Output with json, nocache, wrapper for inputs “set cookie” since setting cookies are more of a output function and simple function to delete all cookies

core/MY_Router.php
provides extensions to CodeIgniter Router to automatically handle controllers in Packages. This also added the “Action” suffix and HTTP method (Post, Put, Delete, Cli (command line), Get (none)). This uses a advanced caching technique to make this lighting fast (no filesystem scanning etc.)…

Orange.php File

core/Orange.php
Provides additional functions to CodeIgniter.
methods include but are not limited to:

ci(class)
a much smarter get_instance()
To get a reference to the CodeIgniter “Super Object” you simple use get_instance()

https://www.codeigniter.com/user_guide/general/ancillary_classes.html?highlight=get_instance#get_instance

But I’ve made it smarter by making it a little more like a dependency injection contain. 
You can autoload a library or model simply by entering it’s name in the function ie.

ci('email')->send();

this will auto load the email library if it’s not already loaded and then call the send method. 
Then you don’t need to call
ci()->load->library(‘email’);
ci()->email->send();

This also becomes a dependency injection contain because the ‘email’ library can be something other than a class named ‘email’ (the default CI way of loading classes)

This “remapping” is handled by the remap entry in /config/autoload.php

$autoload['remap'] = [
	'auth'=>'ldap_auth',
	'o_user_entity'=>'O_ldap_user_entity',
];

in this example ldap_auth is loaded instead of auth when ci(‘auth’) is called. or a mock_auth for testing etc…

site_url(uri,protocol)
smarter site_url
This does everything the CodeIgniter site_url() does

https://www.codeigniter.com/user_guide/helpers/url_helper.html?highlight=site_url#site_url

except this version of the function added a simple find and replace for {} tags. These tags are loaded from /config/paths.php. This then makes it easier to have any reused path in paths.php and by using site_url() load them.

$path = site_url('/{www theme}/assets/css')

config(setting,default)
wrapper for config’s dot_item calls
ci('config')->dot_item()
This makes loaded configuration values a lot easier and it's fully cached. 
config('auth.login h2')
will return the value for /config/auto.php array key 'login h2'.

Just like
$html = ci('config')->dot_item('auth.login h2','Login');
you can also provided a default value. 
$html = config('auth.login h2','Login');

filter(rules,field)
wrapper for running a validation filter calls
ci('validate')->single($rules,$field);

valid(rule,field)
wrapper for running a validation calls
ci('validate')->single($rule,$field);
and returns value of
!ci('errors')->has();

esc(string)
escape " with \" 

e(html)
performs a html encoding of special characters

env(key,default)
wrapper to read environmental variables with a default loads values from the .env file. This should be used in the /config/*.php files. very rarely should you call it in actual code. exceptions might be to load the server environment or debug mode.

view(view,data)
most basic wrapper for loading views
https://www.codeigniter.com/user_guide/general/views.html?highlight=view#adding-dynamic-data-to-the-view

NOTE this ALWAYS returns the rendered view. This does not echo anything.

unlock_session()
more english wrapper for PHP session_write_close() function

console(var,type)
simple browser level debugger shows up in the javascript console wrapper for
echo '<script type="text/javascript">console.'.$type.'('.json_encode($var).')</script>';

l(...)
"raw" logging function 
Simple Logging function for debugging purposes
ALWAYS writes to 
LOGPATH.'/orange_debug.log'

atomic_file_put_contents(file path,content)
PHP necessary atomic file writing
Writes to a file without the needed to worry about another process loading it write it's still being written to.

remove_php_file_from_opcache(full path)
APC / OPcache friendly Remove from cache function
removes a file from opcache or apache without worrying about if it's actually loaded

convert_to_real(value)
convert_to_string(value)
Converting to and from values
Try to convert a value to it's real type this is nice for pulling string from a database such as configuration values stored in string format

simplify_array(array,key,value)
collapse a array with multiple values into a single key=>value pair
this will collapse a array with multiple values into a single key=>value pair

cache(key,closure,ttl)
Wrapper function to sue the currently loaded cache library in a closure fashion 

cache_ttl(use window)
Get the current Cache Time to Live with optional "window" support to negate a cache stamped

delete_cache_by_tags(tags)
Delete cache records based on dot notation "tags" therefore if you have a cache keys of:

acl.users.database.table
acl.groups.database.table
food.database.table
colors.file
colors.table
colors_status.table

You can use different tags
delete_cache_by_tags('acl')
delete_cache_by_tags('acl','user','roles')
delete_cache_by_tags('acl.user.roles')
delete_cache_by_tags(['acl','user','roles'])
if the cache key has one or more matching tag delete the record

Libraries

Auth
libraries/Auth.php
provides the authorization for users. Functions include
login(user identifier, password)
logout()
refresh_userdata(user identifier, save session)

Cache Export
libraries/Cache_export.php
provides the library to cache to the file system

Cache Request
libraries/Cache_request.php
provides the library to create a cache for the current request (in stored in PHP memory)

Errors
libraries/Errors.php
provides a unified library to register errors for further processing

Event
libraries/Event.php
provides the library to provides events in your app
methods include 
register(name, closure, priority)
trigger(name, a#...)
count(name)
has(name)

Filters
libraries/Filter_base.php
provides the abstract base class for all filters and extends validate_base class (just a basic placeholder)
no need to focus on in the short term

libraries/filters/
folder to contain filters
all filters start with Filter_*.php

Orange Autoload Files
libraries/Orange_autoload_files.php
provides generates the autoload cache file of controllers, libraries, models, classes, etc…

Pages
libraries/Page.php
provides the heavy lifting of building HTML Pages.
methods include but are not limited to:
title(title)
set the pages title if different than the default
meta(attribute, name, content, priority)
add additional meta data
body_class(class, priority)
add a class to the body
render(view, data)
render the page and send it’s output to the output class
view(view file, data, return)
basic MVC view function
https://www.codeigniter.com/user_guide/libraries/loader.html#CI_Loader::view
data(name, value)
attach data to a page from any where in the application
icon(image path)
change the default icon
css(file, priority)
add additional links to page
link_html(file)
create and return <link> html
style(style, priority)
add style to page
js(file, priority)
add additional javascript to a page
script_html(file)
create and return <script> html
js_variable(key, value, priority, raw) & js_variables(array)
add a javascript variable to the page
script(script, priority)
add additional scripts to a page
domready(script, priority)
add javascript to the domready section
ary2element(element, attributes, wrapper)
convert PHP array to HTML element
convert2attributes(attributes, prefix, strip_empty)
convert PHP array to HTML attributes
set_priority(priority)
set priority to added elements
reset_priority()
reset element priority to default 50
Pear View Plugins
libraries/Pear_plugin.php
base class for pear plugins. Really only provides a _convert2attributes() for all children objects 

libraries/Pear.php
provides the HTML View Pear “plugin” functions (to be used in view only)
each plugin has really only 2 methods:
it’s class constructor __construct and render()
the constructor is called when the plugin is loaded for the first time
if a plugin just adds for example css or js to a page you can include it with the plugins() & plugin() methods
if your plugin is used in a view to “do” something you simple call pear::foobar($foo,23) which will automatically load the “foobar” plugin (which of course called the constructor if it’s present) and then sends $foo and 23 to the render method. the render method can then return something which can be echoed. Plugins should “echo” directly but instead return a value which can be echoed.
<?=pear::foobar($foo,23) ?>

Built in Pear methods include but are not limited to:
All of the CodeIgniter Helpers functions for html, form, date, inflector, language, number, text
in additional you can call the form helper functions without the form_ prefix
so pear::form_input() and pear::input() are the same thing

Included Pear Methods
pear::section(name,value)
start a page variable section with the supplied name
pear::parent(name)
append to prepend to the current page variable section without this you will overwrite a page section if it already contains something
pear::end()
end the current page variable section
pear::extends(name)
a view can only extend 1 other view (normally the “base” template)
pear::includes(view, data, name)
include another template into scope
pear::is_extending()
returns the currently template we are extending if any
pear::plugins(name, priority) & plugin(name, priority)
load plugins without actually calling the render function

User Wrapper
libraries/User.php
Static wrapper for the orange user object.
no need to focus on in the short term since it’s just a 7 line wrapper

Validation
libraries/Validate_base.php
provides the abstract base class for all validations and filters
it provides the basic method for length, trim, human, human_plus, strip, is_bol to all it’s children classes

libraries/Validate.php
The heavy lifter for input validation
methods include but are not limited to:
clear()
clear all current errors
attach(name, closure)
attach validation as a closure
die_on_fail(view)
die on first validation fail.
redirect_on_fail(url)
redirect to a different URL on fail.
json_on_fail()
output errors as json on fail.
success()
return boolean true or false
variable(rules, field, human)
easy way to run a validation on a variable
request(rules, key, human)
easy way to run a validation on a request value (via a key)
run(rules, fields, human)
auto detects a single or multiple validations
single(rules, fields, human)
run a single validation
multiple(rules, fields)
run multiple validations

libraries/validations/
folder to contain validations
all validations start with Validate_*.php

Wallet
libraries/Wallet.php
wallet provides additional features to CodeIgniter Flash Messaging as well as some other session based functions.
methods include but are not limited to:
pocket(name, value)
a more generic version of cache_request’s features it’s both a getter and setter
snapdata(newdata, newval)
set session data and leave it there for up to 1 hour or until it read
get_snapdata(key)
get session data and remove
keep_snapdata(key)
get session data and do not remove
msg(msg, type, redirect)
set a flash msg with additional features such as color & redirect
This uses custom CSS & Javascript to show OS X like “alerts” in a bootstrap
https://getbootstrap.com/docs/3.3/components/#alerts
stash()
stores request array
unstash()
retrieves and restores request array

Database Model Base Class
models/Database_model.php
This provides the reusable methods for actual database table models
methods include but are not limited to:
get_tablename()
return models tablename
get_primary_key()
return models primary key
get_soft_delete()
is this table using soft deletes?
with_deleted()
select with deleted
only_deleted()
select only deleted
as_array()
return as a array not an object
column(name)
select a single column
on_empty_return(return)
if nothing found return…
get(primary value)
select single record
get_by(where)
select single record with filter
get_many()
select multiple records
get_many_by(where)
select multiple records with filter
insert(data)
insert record
update(data)
update record
update_by(data, where)
update record with filter
delete(arg)
delete record
delete_by(where)
delete record with filter
delete_cache_by_tags()
deleted cache by tags
catalog(array key, select columns, where, order by, cache key, with deleted)
select records for a data array
exists(arg)
test if record exists with filter
count()
count records
count_by(where)
count records with filter
index(order by, limit, where, select)
select for “index” table view

Models
models/entities/
folder of model record entities
Entities include
O_permission_entity.php
O_role_entity.php
O_user_entity.php

models/Model_entity.php
provides the abstract base class for all model entities (just a basic placeholder)
provides a save() method to have a entity save itself

models/O_permission_model.php
Model for permissions table

models/O_role_model.php
Model for roles table

models/O_setting_model.php
Model for settings table

models/O_user_model.php
Model for users table

models/traits/
folder of traits models can inherit
SkyNET GIT "Flow"

Creating a Example “package”

In this example we are going to create a public and private (administrator) web page for managing and displaying cookies.

Our public url will be at /cookies and the administrator url will be /admin/cookies

First we create our “package” folder and add it to the autoload packages array.

Inside the /packages folder create a “cookies” folder. This will contain out cookies package.

Then add that folders path to the /config/autoload.php packages array.

Create the Controllers

Then create the controllers, model, and view folders (see picture below).

for the public URL create

/cookies/controllers/CookiesController.php

for the private administrator view create a folder admin inside the controllers folder and inside that create another
/cookies/controllers/admin/CookiesController.php

This should give you something like this:

We will work on the administrator controller first so inside 

/cookies/controllers/admin/CookiesController.php

enter the following

<?php
class CookiesController extends MY_Controller {
	use admin_controller_trait;
	public $controller_model = 'cookies_model';
	public $controller = 'cookies';
	public $controller_path = '/admin/cookies';
	public $controller_title = 'Cookie';
	public $controller_titles = 'Cookies';
} /* end controller */

Because we are using the Administrator Controller Trait we automatically get all of it’s methods.

If you want to look at the methods you can find them in the Controllers Traits folder this one happens to come with the orange theme therefore it’s in the orange theme controllers traits folder. You of course can create your own in your controllers folder.

/packages/projectorangebox/theme-orange/controllers/traits/admin_controller_trait.php

Create the model

First create the database table

This can be anything you want including secondary keys to additional tables.
In this example we are going to keep it simple.

 
Then for the model
/models/Cookies_model.php
you can enter the following:

<?php
class Cookies_model extends Database_model {
	protected $table = 'cookies_example';
	protected $rules = [
		'id' => ['field' => 'id','label' => 'Id','rules' => 'required|integer|max_length[10]|less_than[10000]|filter_int[10]'],
		'name' => ['field' => 'name','label' => 'Name','rules' => 'required|max_length[128]|filter_input[128]'],
		'size' => ['field' => 'size','label' => 'Size','rules' => 'if_empty[0]|integer|filter_int[4]'],
		'price' => ['field' => 'price','label' => 'Price','rules' => 'required|float'],
		'color' => ['field' => 'color','label' => 'Color','rules' => 'max_length[8]|filter_input[8]'],
	];
} /* end class */

This will tell the Database_model parent class what the name of the table you want to use is as well as the rules for the columns.

Create the Administrator list and detail views.

So the Controller can automatically pick up the view files automatically you place them in the same path structure as the controller and it’s method

So in the index.php (list view) put the following. There is nothing special about this since it’s plain old HTML, PHP and BootStrap CSS.

/views/admin/cookies/index.php

<? pear::extends('_templates/orange_admin') ?>
<? pear::section('section_container') ?>
<div class="row">
	<div class="col-md-6"><h3><?=$controller_titles ?></h3></div>
	<div class="col-md-6">
		<div class="pull-right">
	  		<?=pear::table_search_field() ?>
			<? if (user::has_permission('url::/admin/configure/tooltips::index~post')) { ?>
				<?=pear::new_button($controller_path.'/details','New '.$controller_title) ?>
  		<? } ?>
		</div>
	</div>
</div>
<div class="row">
		<table class="table table-sticky-header table-search table-sort table-hover">
		<thead>
			<tr class="panel-default">
				<th class="panel-heading">Name</th>
				<th class="panel-heading text-center">Size</th>
				<th class="panel-heading text-right">Price</th>
				<th class="panel-heading text-center">Color</th>
				<th class="panel-heading text-center nosort">Actions</th>
			</tr>
		</thead>
		<tbody>
			<? foreach ($records as $row) { ?>
			<tr>
				<td><?=e($row->name) ?></td>
				<td class="text-center"><?=pear::sprintf($row->size,'%d oz.') ?></td>
				<td class="text-right"><?=pear::sprintf($row->price,'$%01.2f') ?></td>
				<td class="text-center"><?=pear::color_block($row->color) ?></td>
				<td class="text-center actions">
					<?=pear::edit_button($controller_path.'/details/'.bin2hex($row->id)) ?>
					<?=pear::delete_button($controller_path,['id'=>$row->id]) ?>
				</td>
			</tr>
			<? } ?>
		</tbody>
	</table>
</div>
<? pear::end() ?>


This should give you something like this.
/views/admin/cookies/details.php

<? pear::extends('_templates/orange_admin') ?>
<? pear::section('section_container') ?>
<?=pear::open_multipart($controller_path,['class'=>'form-horizontal','method'=>$form_method,'data-success'=>'Record Saved|blue'],['id'=>$record->id]) ?>
	<div class="row">
		<div class="col-md-6"><h3><?=$ci_title_prefix ?> <?=$controller_title ?></h3></div>
	  <div class="col-md-6">
	  	<div class="pull-right">
				<?=pear::goback_button($controller_path) ?>
	  	</div>
	  </div>
	</div>
	<hr>
	<div class="form-group">
		<?=pear::field_label('cookies_model','name') ?>
		<div class="col-md-4">
			<?=pear::input('name',$record->name,['class'=>'form-control input-md']) ?>
		</div>
	</div>
	<div class="form-group">
		<?=pear::field_label('cookies_model','size') ?>
		<div class="col-md-2">
			<div class="input-group">
				<?=pear::input('size',$record->size,['class'=>'form-control input-md','data-mask'=>'int']) ?>
				<div class="input-group-addon">oz</div>
			</div>
		</div>
	</div>
	<div class="form-group">
		<?=pear::field_label('cookies_model','price') ?>
		<div class="col-md-2">
			<div class="input-group">
				<div class="input-group-addon">$</div>
				<?=pear::input('price',$record->price,['class'=>'form-control input-md','data-mask'=>'money']) ?>
			</div>
		</div>
	</div>
	<div class="form-group">
		<?=pear::field_label('cookies_model','color') ?>
		<div class="col-md-2">
			<?=pear::color_picker('color',$record->color,['class'=>'form-control input-md']) ?>
		</div>
	</div>
	<div class="form-group">
		<div class="col-md-12">
			<div class="pull-right">
				<?=pear::button(null,'Save',['class'=>'js-button-submit keymaster-s btn btn-primary']) ?>
			</div>
		</div>
	</div>
<?=pear::close() ?>
<? pear::end() ?>

Again just plain old HTML, PHP and BootStrap CSS.

Which should give you something like this.

Finally we need a “public” controller and it’s view.

In the public controller at
/packages/cookies/controllers/CookiesController.php
add the following.

<?php

class CookiesController extends MY_Controller {
	public function indexAction() {
		ci('page')->render(null,['records'=>ci('cookies_model')->get_many()]);
	}
} /* end controller */

This will load the public view with the cookie records.

Again just plain old HTML, PHP and BootStrap CSS.

<? pear::extends('_templates/orange_default') ?>
<? pear::section('section_container') ?>
<h3>Cookies</h3>
<table class="table">
<tr>
	<th class="text-center">#</th>
	<th>Name Of Cookie</th>
	<th class="text-center">Size</th>
	<th class="text-right">Price</th>
	<td class="text-center">Color</td>
</tr>
<? foreach ($records as $record) { ?>
<tr>
	<td class="text-center"><?=e($record->id) ?></td>
	<td><?=e($record->name) ?></td>
	<td class="text-center"><?=pear::sprintf($record->size,'%d oz.') ?></td>
	<td class="text-right"><?=pear::sprintf($record->price,'$%01.2f') ?></td>
	<td class="text-center"><?=pear::color_block($record->color) ?></td>
</tr>
<? } ?>
</table>
<? pear::end() ?>


Finally this should give you the following folder structure


HMVC CodeIgniter / Orange

How to find a Controller, library, model or view in a HMVC framework

“Hierarchical model–view–controller (HMVC) is a software architectural pattern, a variation of model–view–controller (MVC)”

https://en.wikipedia.org/wiki/Hierarchical_model–view–controller

In MVC the Models, Views and Controllers are in a single Structure (folder).

In HMVC the Models, Views and Controllers are in a multiple Structures (folders) and loaded in a Hierarchical manner. This allows you to organize your project and code into smaller “packages”. Loading these is done in a Hierarchical fashion.

This has many advantages some of which are:
Reusability (reuse a package in another project)
Organization (both GIT and File management)
Scaleability https://inviqa.com/blog/scaling-web-applications-hmvc


There are many articles about this on the internet
http://lmgtfy.com/?q=HMVC

How do know if something is being “overridden”?

If you look into the Application Configuration file “autoload.php” you will see a configuration setting for “packages”.

https://www.codeigniter.com/user_guide/libraries/config.html?highlight=autoload#auto-loading

These are the paths of each of the packages loaded by CodeIgniter. These are “searched” in  order when looking for a specific file.

$autoload['packages'] = array(
	ROOTPATH.'/packages/projectorangebox/extra-validations',
	ROOTPATH.'/packages/projectorangebox/forgot',
	ROOTPATH.'/packages/projectorangebox/remember',
	ROOTPATH.'/packages/projectorangebox/register',
	ROOTPATH.'/packages/projectorangebox/opcache',
	ROOTPATH.'/packages/projectorangebox/config-viewer',
	ROOTPATH.'/packages/projectorangebox/librarian',
	ROOTPATH.'/packages/projectorangebox/migrations',
	ROOTPATH.'/packages/projectorangebox/scaffolding',
	ROOTPATH.'/packages/projectorangebox/cache-viewer',
	ROOTPATH.'/packages/projectorangebox/login-success',
	ROOTPATH.'/packages/projectorangebox/tooltips',
	ROOTPATH.'/packages/projectorangebox/handlebars',
	ROOTPATH.'/packages/projectorangebox/general_addons',
	ROOTPATH.'/packages/projectorangebox/tasks',
	ROOTPATH.'/packages/projectorangebox/orange',
	ROOTPATH.'/packages/projectorangebox/theme-orange',
	ROOTPATH.'/packages/projectorangebox/user_msgs',
);

NOTE: In addition to what you see here take note that the Application folder is prepended to the beginning and the system is appended to the end.

This way anything in the Application package (folder) can override anything in any other package and the system package (folder) is the last place searched for something.
So the packages search array actually looks like this

$autoload['packages'] = array(
	ROOTPATH.'/application',
	ROOTPATH.'/packages/projectorangebox/extra-validations',
	ROOTPATH.'/packages/projectorangebox/forgot',
	ROOTPATH.'/packages/projectorangebox/remember',
	ROOTPATH.'/packages/projectorangebox/register',
	ROOTPATH.'/packages/projectorangebox/opcache',
	ROOTPATH.'/packages/projectorangebox/config-viewer',
	ROOTPATH.'/packages/projectorangebox/librarian',
	ROOTPATH.'/packages/projectorangebox/migrations',
	ROOTPATH.'/packages/projectorangebox/scaffolding',
	ROOTPATH.'/packages/projectorangebox/cache-viewer',
	ROOTPATH.'/packages/projectorangebox/login-success',
	ROOTPATH.'/packages/projectorangebox/tooltips',
	ROOTPATH.'/packages/projectorangebox/handlebars',
	ROOTPATH.'/packages/projectorangebox/general_addons',
	ROOTPATH.'/packages/projectorangebox/tasks',
	ROOTPATH.'/packages/projectorangebox/orange',
	ROOTPATH.'/packages/projectorangebox/theme-orange',
	ROOTPATH.'/packages/projectorangebox/user_msgs',
	ROOTPATH.’/vendor/codeigniter/framework/system’,
);

Now your probably saying! All that “searching” must take forever and slow down the Framework!
Fear Not!
Every time the application is loaded it looks for a cache file at 

/var/cache/autoload_files.php

If the file isn’t there it is created.

On a production system this only needs to happen ONCE because new files aren’t being created. As part of the deployment script this file is deleted so it can be refreshed (once). See deploy.json

https://github.com/dmyers2004/deploy

On a development system this is done every time because your are “developing” new files and moving stuff around and renaming files etc… This still happens in less than 1/2 second thou.So back to our original question.
How do know if something is being “overridden”?

Well I’ll give you 3 examples.

In this example the User Index view is overridden in the Application folder to give it addition features that the original User Index view in the project orange box theme didn’t provide.

Using a editor like Espresso or Sublime.

You simply search for the file you are looking for:
You can see all of the index.php files matching our criteria.

In this case you can see: 

application/views/admin/users/index.php

Which as I mentioned earlier is higher in the Hierarchy over the original which is at 

packages/projectorangebox/…ws/admin/users/index.php

Therefore the application one that is used by the framework.Another way is to search on the …/admin/utilities/config-viewer/autoload webpage (part of the config-view package so that must be loaded).


You can preform a “find” in your browser. To get something like above.

admin/users/index

view is being loaded from this file.

/var/www/www/app/application/views/admin/users/index.php

Finally if you don’t have access to the config-viewer autoload webpage.
You can search the actual autoload_files.php file.
In closing…

Now fortunately we don’t need to override models, views, or controllers much but this should help you understand HMVC a little bit better when it comes to loading files.Basic Package Folder Structure

The basic CodeIgniter HMVC package matches the CodeIgniter Application folder. By breaking your projects into packages it allows you to manage your projects in smaller “parts” and allow you to reuse packages in multiple CodeIgniter projects easily.

Note: each package can have it’s own config folder and files just like the CodeIgniter Application folder but, I like to keep all the config files in the Application config folder so you don’t need to search for configuration in different packages. This of course can easily change based on the teams best practices.

CodeIgniter can just like most MVC frameworks allow you to write custom routes to point to custom controllers and methods. CodeIgniter also like many MVC frameworks can automatically route URLs to matching Controllers and Methods.

https://www.codeigniter.com/user_guide/general/urls.html

A basic database table “List” and “Details” view can be setup with the following controller.

<?php

class TooltipsController extends MY_Controller {
	use admin_controller_trait;

	public $controller         = 'tooltips';
	public $controller_path    = '/admin/configure/tooltips';
	public $controller_title   = 'Tooltip';
	public $controller_titles  = 'ToolTips';
	public $controller_model   = 'a_tooltips_model';
} /* end controller */

This will inherit	all of the methods from the admin_controller_trait.
This trait comes with the orange theme and is not part of the orange package because each theme/project might have different requirements for displaying and editing the actual data.
Orange provides standard models for users, roles, permissions, and settings but does not enforce how you display or edit the data. It only enforces that the data set into the model passes the models validation.

The class properties are then setup so admin_controller_trait can do some logic.
Most of this could be figured out by the controller but at the cost of resources and speed.

$controller - name of the controller
$controller_path -  url of the controller
$controller_title - singular title used in displays
$controller_titles - plural title used in displays
$controller_model - the model to interact with in the admin_controller_trait methods

<?php
class A_tooltips_model extends Database_model {
	protected $table = 'addon_tooltips';
	protected $rules = [
		‘id' => ['field' => 'id','label' => 'Id','rules' => 'required|integer|max_length[10]|less_than[4294967295]|filter_int[10]'],
		‘selector' => ['field' => 'selector','label' => 'Selector','rules' => 'required|max_length[255]|filter_input[255]'],
		‘text' => ['field' => 'text','label' => 'Tooltip','rules' => 'required|max_length[255]|filter_input[255]'],
		‘active' => ['field' => 'active','label' => 'Active','rules' => 'if_empty[0]|in_list[0,1]|filter_int[1]|max_length[1]|less_than[2]'],
	];
} /* end class */


By extending the orange frameworks Database_model and setting up a few properties you can interact with database table.

$table - database table name
$rules - validation rules basically follows the validation library guidelines
https://www.codeigniter.com/user_guide/libraries/form_validation.html#setting-rules-using-an-array

“list” view

Using Bootstrap CSS and some theme pear plugins you can setup a completely customizable list view in about 5 minutes.
(normally can just cut in paste from this example)

<? pear::extends('_templates/orange_admin') ?>
<? pear::section('section_container') ?>
<div class="row">
  <div class="col-md-6"><h3><i class="fa fa-comment"></i> <?=$controller_titles ?></h3></div>
  <div class="col-md-6">
  	<div class="pull-right">
  		  		<?=pear::table_search_field() ?>

			<? if (user::has_permission('url::/admin/configure/tooltips::index~post')) { ?>
				<?=pear::new_button($controller_path.'/details','New '.$controller_title) ?>
  		<? } ?>
  	</div>
  </div>
</div>
<div class="row">
		<table class="table table-sticky-header table-search table-sort table-hover">
			<thead>
				<tr class="panel-default">
					<th class="panel-heading"><?=pear::field_human('a_tooltips_model','selector') ?></th>
					<th class="panel-heading"><?=pear::field_human('a_tooltips_model','text') ?></th>
					<th class="panel-heading text-center"><?=pear::field_human('a_tooltips_model','active') ?></th>
					<th class="panel-heading text-center nosort">Actions</th>
				</tr>
			</thead>
		<tbody>
			<? foreach ($records as $row) { ?>
			<tr>
				<td><?=e($row->selector) ?></td>
				<td><?=e($row->text) ?></td>
				<td class="text-center"><?=pear::fa_enum_icon($row->active) ?></td>
				<td class="text-center actions">
					<?=pear::edit_button($controller_path.'/details/'.bin2hex($row->id)) ?>
					<?=pear::delete_button($controller_path,['id'=>$row->id]) ?>
				</td>
			</tr>
			<? } ?>
		</tbody>
	</table>
</div>
<? pear::end() ?>

Using Bootstrap CSS and some theme pear plugins you can setup a completely customizable details view in about 5 minutes. (normally can just cut in paste from other views)

“details” view

<? pear::extends('_templates/orange_admin') ?>
<? pear::section('section_container') ?>
<?=pear::open_multipart($controller_path,['class'=>'form-horizontal','method'=>$form_method,'data-success'=>'Record Saved|blue'],['id'=>$record->id]) ?>
	<div class="row">
		<div class="col-md-6"><h3><?=$ci_title_prefix ?> <?=$controller_title ?></h3></div>
	  <div class="col-md-6">
	  	<div class="pull-right">
				<?=pear::goback_button($controller_path) ?>
	  	</div>
	  </div>
	</div>
	<hr>
	<!-- Text input-->
	<div class="form-group">
		<?=pear::field_label('a_tooltips_model','selector') ?>
		<div class="col-md-4">
			<?=pear::input('selector',$record->selector,['class'=>'form-control input-md','autocomplete'=>'off']) ?>
		</div>
	</div>
	<!-- Text input-->
	<div class="form-group">
		<?=pear::field_label('a_tooltips_model','text') ?>
		<div class="col-md-4">
			<?=pear::input('text',$record->text,['class'=>'form-control input-md','autocomplete'=>'off']) ?>
		</div>
	</div>
	<!-- Text input-->
	<div class="form-group">
		<label class="col-md-3 control-label">&nbsp;</label>
		<div class="col-md-9">
			<?=pear::checker('active', 1,$record->active) ?> <?=pear::field_human('a_tooltips_model','active') ?>
		</div>
	</div>
	<!-- Submit Button -->
	<div class="form-group">
		<div class="col-md-12">
			<div class="pull-right">
				<?=pear::button(null,'Save',['class'=>'js-button-submit keymaster-s btn btn-primary']) ?>
			</div>
		</div>
	</div>
<?=pear::close() ?>
<? pear::end() ?>

CodeIgniter / Orange Command Line
As well as calling an applications Controllers via the URL in a browser they can also be loaded via the command-line interface (CLI).

https://www.codeigniter.com/userguide3/general/cli.html

Normally I place my command line scripts in a "cli" folder. This organizes them a little bit better and give you the following url structure.

php index.php cli/some_command

I have provided a number of building commands. One of the most helpful is the "Help" command which you can access by calling php index.php cli/help
Below is some of it's output.
Note: when adding Command line Methods to a controller remember to add the Cli Method
public function downCliAction() {...}

This not only protects them from being called via the web browser by also organizes them.
CodeIgniter Migrations

CodeIgniter supports Migrations natively you can read more about it here

https://www.codeigniter.com/userguide3/libraries/migration.html?highlight=migrations

I made a change which provides support for migrations in packages because normally a application only has one migration folder.

I made a additional change in which you must return TRUE on success and FALSE on failure from your migration UP and Down methods (see examples in zip). This will automatically allow/stop further migrations when you are running multiple one after another.

I have provided Command Line options which include:

cli/migrate/up
cli/migrate/down ???
cli/migrate/latest
cli/migrate/version ???
cli/migrate/find
cli/migrate/create "migration description"


These also include all packages so you can do the following

cli/migrate/up packages/projectorangebox/scaffolding
cli/migrate/down packages/projectorangebox/scaffolding ???
cli/migrate/latest packages/projectorangebox/scaffolding
cli/migrate/version packages/projectorangebox/scaffolding ???
cli/migrate/find
cli/migrate/create packages/projectorangebox/scaffolding "migration description"


You can see from this example I migrated to version 4 in the "scaffolding" package.

Migration Included Methods

The Base Migration Class
Migration_base.php
includes some of the following methods

_copy_config()
_unlink_config()

_link_public()
_unlink_public()

_add_rw_folder()
_remove_rw_folder()

_describe_table()
_db_has_column()

_find_n_replace()

The following models also have migration_add() and migration_remove() methods:
O_permission_model
O_role_model
O_setting_model
O_nav_model (included with orange-theme)
```
