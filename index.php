<?php

require_once('classes' . DIRECTORY_SEPARATOR . 'lookup.php');

$serverList = dirname(__FILE__) . "/misc/servers/servers.json";

if ($_POST) (new Lookup())->run($_POST, $serverList);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Bulk Domain Availability</title>
    <link rel="stylesheet" type="text/css" href="styles/simple-grid.css">
    <link rel="stylesheet" type="text/css" href="styles/style.css">

    <style type="text/css" media="screen" id="css-clm-whois">
        .clm-whois {
            display: none;
        }
    </style>
    <style type="text/css" media="screen" id="css-clm-exec-time">
        .clm-exec-time {
            display: none;
        }
    </style>
    <style type="text/css" media="screen" id="css-clm-resource">
        .clm-resource {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Start header -->
        <header id="header" class="">
            <h1>Bulk Domain Availability</h1>
        </header>
        <!-- / End header -->

        <form action="" method="post" accept-charset="utf-8" id="availability-form">
            <div class="row">
                <div class="col-8">
                    <textarea name="domains" placeholder="Enter domain name with/without extension and separated by spaces or new lines, e.g.:&#10;example.com&#10;facebook twitter&#10;anything" required></textarea>
                </div>
                <div class="col-4">
                    <select name="tlds" multiple required>
                        <?= Lookup::htmlOptions($serverList); ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-3">
                    <input type="text" name="prefix" placeholder="Enter prefix e.g.: the" />
                </div>
                <div class="col-3">
                    <input type="text" name="suffix" placeholder="Enter suffix e.g.: online" />
                </div>
                <div class="col-2">
                    <input type="number" name="max-length" placeholder="Max length" min="1" max="63" />
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <input type="checkbox" name="combine" value="1" id="combine-a">
                    <label for="combine-a">Combine words</label>
                </div>
                <div class="col-2">
                    <input type="checkbox" name="combine" value="2" id="combine-b">
                    <label for="combine-b">Combine "-" as separator</label>
                </div>
                <div class="col-2">
                    <input type="checkbox" name="cache" value="1" checked="checked" id="cache">
                    <label for="cache">Save cache</label>
                </div>
                <div class="col-6">
                        <div class="col-4 left">
                            <button type="button" id="clear-cache" class="error" style="word-break:keep-all;">Clear Cache</button>
                        </div>
                        <div class="col-4 center">
                            <button type="reset" class="warning">Reset</button>
                        </div>
                        <div class="col-4 right">
                            <button type="submit" id="search">Search</button>
                            <button type="button" id="pause" class="hidden">Pause</button>
                            <button type="button" id="resume" class="hidden">Resume</button>
                            <button type="button" id="stop" class="error hidden">Stop</button>
                        </div>
                </div>
            </div>
        </form>

        <div class="row result hidden">
            <div class="col-12">
                <div class="result-title">
                    <h2>Result</h2>
                </div>
            </div>
            <div class="col-2">
                <h5>
                    Progress:
                </h5>
                <div class="clearfix"></div>
                <h5>
                    <span id="domains-checked">0</span>/<span id="domains-length">0</span> (<span id="progress">0</span>)%
                </h5>
                <div class="clearfix"></div>
                <h5>Checking:</h5>
                <div class="clearfix"></div>
                <h5 id="checking"><span></span></h5>
            </div>
            <div class="col-2">
                <h5 id="available-counter" class="success">Available: <span>0</span></h5>
                <div class="clearfix"></div>
                <h5 id="unavailable-counter" class="warning">Unavailable: <span class="warning">0</span></h5>
                <div class="clearfix"></div>
                <h5 id="error-counter" class="error">Error: <span class="error">0</span> <label class="retry-error hidden">(<a href="javascript:void(0)" id="retry-error">retry</a>)</label></h5>
            </div>
            <div class="col-2">
                <input type="checkbox" name="hide-unavailable" value="1" id="hide-unavailable">
                <label for="hide-unavailable">Hide unavailable</label>
            </div>
            <div class="col-2">
                <input type="checkbox" name="hide-whois" value="1" checked="checked" id="hide-whois">
                <label for="hide-whois">Hide whois server</label>
            </div>
            <div class="col-2">
                <input type="checkbox" name="hide-exec-time" value="1" checked="checked" id="hide-exec-time">
                <label for="hide-exec-time">Hide execute time</label>
            </div>
            <div class="col-2">
                <input type="checkbox" name="hide-resource" value="1" checked="checked" id="hide-resource">
                <label for="hide-resource">Hide resource</label>
            </div>
            <div class="clearfix"></div>
            <div class="col-12">
                <table id="result-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Domain</th>
                            <th>Availability</th>
                            <th>Length</th>
                            <th>Message</th>
                            <th class="clm-whois">Whois Server</th>
                            <th class="clm-resource">Resource</th>
                            <th class="clm-exec-time">Execute Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Start footer -->
        <div class="row">
            <div class="col-12">
                <footer>
                    <span>Copyright &copy; <?= date('Y') ?> <a href="https://github.com/abaron/bulk-domain-availability" target="_blank">abaron</a></span>
                </footer>
            </div>
        </div>
        <!-- / End footer -->
    </div>

    <script src="scripts/jquery.js" type="text/javascript"></script>
    <script src="scripts/table2csv.js" type="text/javascript"></script>
    <script src="scripts/script.js" type="text/javascript"></script>
</body>
</html>