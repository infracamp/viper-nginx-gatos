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

$config = "server{listen 80; listen [::]:80; server_name default; location / { root /var/www/html/nginxroot; } }";
$config .= "\nserver{listen 80; listen [::]:80; server_name " . CONF_CLUSTER_HOSTNAME . "; location / { proxy_pass http://localhost:4000/; } }";

foreach ($services as $serviceName => $service) {
    $inspectData = json_decode(phore_exec("sudo docker service inspect --format '{{json . }}' :ID", $service), true);

    $serverNames = "$serviceName" . CONF_DEFAULT_HOSTNAME;
    foreach ($inspectData["Spec"]["Labels"] as $lName => $lValue) {
        if ($lName !== "cf_domain")
            continue;
        $serverNames .= " $lValue";
    }

    $config .= "\n";
    if ($serviceName == gethostbyname($serviceName)) {
        // cannot resolve
        continue;
    }
    try {
        phore_http_request("http://$serviceName")->send(true);
    } catch (\Exception $e) {
        continue;
    }

    $config .= "
    server {
        listen 80; listen [::]:80; server_name $serverNames; 
        location / {
            proxy_set_header Host \$host;   
            proxy_set_header X-Real-IP \$remote_addr;
            proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for; 
            proxy_pass http://{$serviceName}:80/; 
        } 
    }
    ";
}

if ($sha !== sha1($config)) {
    echo "\nRestart required.";
    file_put_contents(NGINX_CONF, $config);
    phore_exec("sudo service nginx restart");
}




