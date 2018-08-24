<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 24.08.18
 * Time: 11:02
 */

namespace Rudl;
use Infracamp\Gatos\DockerCmd;
use Infracamp\Gatos\GitRepo;

require __DIR__ . "/../vendor/autoload.php";


$docker = new DockerCmd();

echo "Initializing cluster...";

try {
    $config = $docker->getConfig(RUDL_CONFIG_NAME);

    echo "\nCluster already initialized. Exit!";
    exit;
} catch (\Exception $e) {
    echo "\nFailed to load config:" . $e->getMessage();
    echo "\nTrying to initialize cluster...";
}

//$docker->setConfig(RUDL_CONFIG_NAME, ["some config"]);

phore_exec("ssh-keygen -q -t ed25519 -P '' -f /tmp/idx");

$config = [
    "hostname" => "cluster.name",
    "git_repo_config" => "ssh://git@gitlab.com/path/to/git.git",
    "git_repo_config_file" => "cluster.config.yml",
    "ssh_key_private" => file_get_contents("/tmp/idx"),
    "ssk_key_pub" => file_get_contents("/tmp/idx.pub"),
    "config" => []
];

while (true) {
    echo "\nTrying to clone {$config["git_repo_config"]}...";
    $repo = new GitRepo("/tmp/config");
    $repo->setAuthSshPrivateKey($config["ssh_key_private"]);
    try {
        $repo->gitClone();
        break;
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage();
        sleep(30);
    }

}

$docker->setConfig(RUDL_CONFIG_NAME, $config);
