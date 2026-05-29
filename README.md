PHP REST Client for JRS
=======================================

This is a copy of jaspersoft/jrs-rest-php-client with small enhancements.

Introduction
-------------
Using this library you can make requests and interact with the Jasper Reports Server through the REST API in native PHP. This allows you to more easily embed data from your report server, or perform administrative tasks on the server using PHP.

Requirements
-------------
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/sigedi/jasper-rest-client/php)

Installation
-------------
Run `composer require angus/jasper-rest-client` or add the following to your composer.json file:

    {
	    "require": {
		    "angus/jasper-rest-client": "*"
	    }
    }


Security Notice
----------------
This package uses BASIC authentication to identify itself with the server. This package should only be used over a trusted connection between your report server and your web server.

License
--------
Copyright &copy; 2005 - 2014 Jaspersoft Corporation. All rights reserved.
http://www.jaspersoft.com.

Unless you have purchased a commercial license agreement from Jaspersoft,
the following license terms apply:

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Lesser  General Public License for more details.

You should have received a copy of the GNU Lesser General Public  License
along with this program. If not, see <http://www.gnu.org/licenses/>.
