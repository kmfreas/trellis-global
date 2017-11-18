<?php
namespace TrellisHelper;

use Maknz\Slack\Client as Slack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ProvisionServer extends Command
{
    private $slackHook;
    public function configure()
    {
        $this->setName('provision')
            ->setDescription('Provision trellis server environment')
            ->addArgument('environment', InputArgument::REQUIRED, 'Server to provision (staging, production)');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getSlackWebHook($input, $output)
            ->checkForTrellisUpdate($output)
            ->postToSlack($input, $output)
            ->provisionServer($input, $output, $input->getArgument('environment'));
    }

    private function checkForTrellisUpdate(OutputInterface $output)
    {
        $output->writeln('<info>Checking for trellis-global repo update</info>');
        $status = shell_exec("[ $(git rev-parse HEAD) = $(git ls-remote $(git rev-parse --abbrev-ref @{u} | \
        sed 's/\// /g') | cut -f1) ] && echo 'true' || echo 'false'");
        if (trim($status) === 'false') {
            $output->writeln('<error>Trellis global repo is not up to date.  Run a git pull and retry.</error>');
            exit(1);
        }
        return $this;
    }

    public function postToSlack(InputInterface $input, OutputInterface $output)
    {
        $settings = [
            'username' => 'trellis-bot',
            'channel'  => '#trellis-dev',
        ];
        $client = new Slack($this->slackHook, $settings);
        $user   = trim(shell_exec('git config --get user.name')) ?: trim(shell_exec('git config --get user.email'));
        if (empty($user)) {
            $user = trim(shell_exec('hostname'));
        }
        $client->send('Provision started on ' . $input->getArgument('environment') . ' server by ' . $user);
        return $this;
    }

    public function getSlackWebHook(InputInterface $input, OutputInterface $output)
    {
        $dir = getcwd();
        chdir(getcwd() . '/trellis');
        $vault = Yaml::parse(`ansible-vault view group_vars/all/vault.yml`);
        if (!empty($vault) && !empty($vault['slack_webhook'])) {
            $this->slackHook = $vault['slack_webhook'];
        } else {
            $output->writeln('<error>Error reading from vault file. Exiting provision process.</error>');
            exit(1);
        }
        chdir($dir);
        return $this;
    }

    public function provisionServer(InputInterface $input, OutputInterface $output)
    {
        $message = 'Provisioning ' . $input->getArgument('environment') . ' environment';
        $output->writeln('<info>' . $message . '</info>');
        $dir = getcwd();
        chdir(getcwd() . '/trellis');
        passthru('ansible-playbook server.yml -e env=' . $input->getArgument('environment'));
        chdir($dir);
        return $this;
    }
}
