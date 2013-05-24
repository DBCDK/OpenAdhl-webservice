<?php
/** \brief
 *
 * This file is part of OpenLibrary.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * OpenLibrary is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenLibrary is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with OpenLibrary.  If not, see <http://www.gnu.org/licenses/>.
*/


/** include for caching */
require_once("OLS_class_lib/memcache_class.php");


/** include for postgres database-access */
require_once("OLS_class_lib/pg_database_class.php");

/** include for database-access */
require_once("OLS_class_lib/oci_class.php");


/** include ADHL service classes */
require_once("lib/ADHLServer.php");
require_once("lib/pg_db.php");
require_once("lib/helpFunc.php");


// initialize server
$server = new ADHLServer("adhl.ini");

// handle the request
$server->handle_request();

