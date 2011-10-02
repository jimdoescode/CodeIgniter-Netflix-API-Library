CodeIgniter Netflix API Library
===============================

This library will let a user authenticate with netflix and perform actions such as managing your instant and dvd queue as well as rating movies.

For the most up to date documentation checkout my blog at http://jimdoescode.blogspot.com

Usage
------
Copy the files under your application directory. Then load the library like this:

$params['key'] = 'NETFLIX API KEY';
$params['secret'] = 'NETFLIX API SECRET';

$this->load->library('netflix', $params);

License
-------
This library is licensed under the MIT license. 

Sparks
------
You can also use this library with Sparks. Simply install using sparks then call.

$this->load->spark('netflix/1.0.0');

Then load the library as specified in the usage.

