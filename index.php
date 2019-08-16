<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

error_reporting(E_ALL & ~E_NOTICE);

require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Neurozone\Inbooks;

if (defined('LOGS_DAYS_TO_KEEP')) {
    $handler = new RotatingFileHandler(__DIR__ . '/logs/inbooks.log', LOGS_DAYS_TO_KEEP);
} else {
    $handler = new RotatingFileHandler(__DIR__ . '/logs/inbooks.log', 7);
}

$stream = new StreamHandler(__DIR__ . '/logs/inbooks.log', Logger::DEBUG);

$logger = new Logger('inbooksLogger');
$logger->pushHandler($handler);
$logger->pushHandler($stream);

try {
    $book = new Inbooks(__DIR__ . '/data/barbey_d_aurevilly_les_diaboliques.epub');
} catch (Exception $e) {
}


?>
<!DOCTYPE html>
<html dir=ltr lang=en>
<head>
    <meta charset=utf-8>
    <meta http-equiv=X-UA-Compatible content="IE=edge">
    <title>EPub</title>
</head>

<body>

<?php print_r($book->getMenu()) ;?>

</body>
</html>