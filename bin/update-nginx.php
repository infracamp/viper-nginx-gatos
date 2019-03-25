#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 09.08.18
 * Time: 11:03
 */

namespace NginxUpdater;

use Infracamp\Gatos\DockerCmd;

require __DIR__ . "/../vendor/autoload.php";



$cmd = new DockerCmd();
$services = $cmd->getServiceList();


$sha = "";
if (file_exists(NGINX_CONF)) {
    $sha = sha1_file(NGINX_CONF);
}

$config = "limit_req_zone \$binary_remote_addr zone=global_limit:10m rate=200r/s;";
$config .= "limit_req_zone \$binary_remote_addr zone=manager_limit:10m rate=5r/s;";
$config .= "limit_req_status 429;";

$config .= "\nserver{listen 80; listen [::]:80; server_name default; location / { limit_req zone=global_limit; return 404; } location /404.html { limit_req zone=global_limit;  root /var/www/html/nginxroot/error; internal;} error_page 404 /404.html; }";
$config .= "\nserver{listen 80; listen [::]:80; server_name " . CONF_CLUSTER_HOSTNAME . "; location / { limit_req zone=manager_limit burst=10; proxy_pass http://localhost:4000/; } }";

foreach ($services as $serviceName => $service) {

    try {
        $serviceConfig = $cmd->getParsedConfigLabel($serviceName);
    } catch (\InvalidArgumentException $e) {
        echo "\nError: " . $e->getMessage();
        continue;
    }

    $serverNames = [];
    if (CONF_DEFAULT_HOSTNAME !== "") {
        $serverNames[] = "$serviceName" . CONF_DEFAULT_HOSTNAME;
    }

    if (isset ($serviceConfig["cloudfront"]) && isset($serviceConfig["cloudfront"]["hostnames"])) {
        foreach ($serviceConfig["cloudfront"]["hostnames"] as $curHostname) {
            $serverNames[] = $curHostname;
        }
    }

    if (count($serverNames) == 0) {
        continue;
    }
    sort($serverNames);

    try {


        // False ignores 404 or 500 - but not connection exception
        $return = phore_http_request("http://$serviceName")->send(false);

        $config .= "
        server {
            listen 80; listen [::]:80; 
            server_name " . implode (" ", $serverNames) . "; 
            
            location / {
                limit_req zone=global_limit burst=200 nodelay;
                proxy_set_header Host \$host;
                proxy_set_header X-Real-IP \$remote_addr;
                proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; 
                proxy_pass http://{$serviceName}:80/; 
            } 
        }
        ";

    } catch (\Exception $e) {
        $config .= "
        server{listen 80; listen [::]:80; server_name " . implode (" ", $serverNames) . "; location / {  limit_req zone=global_limit; return 503; } location /503.html { limit_req zone=global_limit; root /var/www/html/nginxroot/error; internal;} error_page 503 /503.html; }
        ";
    }



}

$localhostdown = false;
try {
    phore_http_request("http://localhost/cf_selfcheck.json")->send(true);
} catch (\Exception $e) {
    $localhostdown = true;
    echo "Error: Localhost is down!";
}


if ($sha !== sha1($config) || $localhostdown) {
    echo "\nRestart required.";
    file_put_contents(NGINX_CONF, $config);
    if ($localhostdown)
        phore_exec("sudo service nginx restart");
    else
        phore_exec("sudo service nginx reload");
}




