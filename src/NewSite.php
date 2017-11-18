<?php
namespace TrellisHelper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class NewSite extends Command
{
    private $domain;
    private $theme;
    private $name;
    private $productionVars;
    private $plugins = [
        'simple-history',
        'custom-post-type-ui',
        'wordpress-seo',
        'duplicate-post',
        'ewww-image-optimizer',
        'regenerate-thumbnails',
        'thumbnail-upscale',
        'quick-pagepost-redirect-plugin',
        'fakerpress'
    ];

    public function configure()
    {
        $this->setName('new')
            ->setDescription('Create a new wordpress site')
            ->addArgument('domain', InputArgument::REQUIRED, 'Final domain name of the site');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $message = 'Creating site with domain: ' . $input->getArgument('domain');
        $output->writeln('<info>' . $message . '</info>');

        $this->domain = $input->getArgument('domain');
        $this->name   = substr($this->domain, 0, strpos($this->domain, '.'));
        $this
            // ->checkForTrellisUpdate($output)
            ->ensureSiteUnique($output)
            ->createTempDir($output)
            ->getBedrock($output)
            ->getTheme($input, $output)
            ->buildTheme($output)
            ->requirePlugins($output)
            ->createRepo($input, $output)
            ->addProjectToComposer($output)
            ->setupProductionDatabaseVars($output)
            ->addProjectToTrellis($output)
            ->createProductionDatabase($output)
            ->postConfig($output)
            ->cleanup($output);
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

    private function ensureSiteUnique(OutputInterface $output)
    {
        $output->writeln('<info>Ensuring site does not exist already</info>');
        $json = json_decode(file_get_contents(getcwd() . '/composer.json'), true);
        foreach ($json['require'] as $key => $site) {
            if ($key === '19ideas/' . $this->domain) {
                throw new \Exception('Site already exists');
            }
        }
        return $this;
    }

    private function createTempDir(OutputInterface $output)
    {
        $output->writeln('<info>Create temp dir in ' . getcwd() . '/tmp-trellis-helper</info>');
        if (file_exists(getcwd() . '/tmp-trellis-helper')) {
            shell_exec('rm -rf ' . getcwd() . '/tmp-trellis-helper');
        }
        $message = 'Creating temporary directory in ' . getcwd() . '/tmp-trellis-helper';
        $output->writeln('<info>' . $message . '</info>');
        mkdir(getcwd() . '/tmp-trellis-helper');
        return $this;
    }

    private function getBedrock(OutputInterface $output)
    {
        $message = 'Clone bedrock to ' . getcwd() . '/tmp-trellis-helper/bedrock';
        $output->writeln('<info>' . $message . '</info>');
        shell_exec('git clone --depth=1 https://github.com/roots/bedrock.git ' . getcwd() . '/tmp-trellis-helper/bedrock');
        if (file_exists(getcwd() . '/tmp-trellis-helper/bedrock/.git')) {
            shell_exec('rm -rf ' . getcwd() . '/tmp-trellis-helper/bedrock/.git');
        }

        return $this;
    }

    private function cleanup(OutputInterface $output)
    {
        $output->writeln('<info>Cleaning up...</info>');
        if (file_exists(getcwd() . '/tmp-trellis-helper')) {
            shell_exec('rm -rf ' . getcwd() . '/tmp-trellis-helper');
        }
        return $this;
    }

    private function getTheme(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Downloading theme</info>');
        $helper   = $this->getHelper('question');
        $options  = ['Sage v8.4.2 (bs3, npm, bower, gulp)', 'Sage v8.5.1 (bs4, npm, bower, gulp)', 'Sage v9 (bs4, yarn, webpack, blade)', 'None'];
        $question = new ChoiceQuestion('Pick a theme:', $options, 0);

        $question->setErrorMessage('theme %s is invalid.');
        $theme = $helper->ask($input, $output, $question);
        $output->writeln('You selected: ' . $theme);

        switch ($theme) {
            case $options[0]:
                $this->theme = 'sage-8';
                $file        = $this->downloadArchive('https://github.com/roots/sage/archive/8.4.2.zip', 'sage-8.4.2.zip', $output);
                $this->extractArchive($file);
                rename(getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes/sage-8.4.2', getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes/' . $this->name . '-theme');
                break;
            case $options[1]:
                $this->theme = 'sage-8';
                $file        = $this->downloadArchive('https://github.com/roots/sage/archive/8.5.1.zip', 'sage-8.5.1.zip', $output);
                $this->extractArchive($file);
                rename(getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes/sage-8.5.1', getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes/' . $this->name . '-theme');
                break;
            case $options[2]:
                $this->theme = 'sage-9';
                break;
            default:
                $this->theme = false;
                break;
        }

        return $this;
    }

    private function buildTheme(OutputInterface $output)
    {
        $dir = getcwd();
        switch ($this->theme) {
            case 'sage-8':
                $output->writeln('<info>Building dev dependencies for sage-8 (npm, bower, gulp)</info>');
                chdir(getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes/' . $this->name . '-theme/');
                $json                     = json_decode(file_get_contents(getcwd() . '/assets/manifest.json'), true);
                $json['config']['devUrl'] = 'http://' . $this->name . '.dev';
                file_put_contents(getcwd() . '/assets/manifest.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                shell_exec('npm install');
                shell_exec('bower install');
                shell_exec('gulp');
                $output->writeln('<info>Build complete</info>');
                break;
            case 'sage-9':
                $output->writeln('<info>Get sage-9 from composer</info>');
                chdir(getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes');
                shell_exec('composer create-project roots/sage ' . $this->name . '-theme dev-master');
                chdir(getcwd() . '/' . $this->name . '-theme');
                $output->writeln('<info>Run yarn</info>');
                shell_exec('yarn');
                break;
            default:
                break;
        }
        chdir($dir);
        return $this;
    }

    private function requirePlugins(OutputInterface $output)
    {
        $output->writeln('<info>Add required plugins to project composer.json</info>');
        $json = json_decode(file_get_contents(getcwd() . '/tmp-trellis-helper/bedrock/composer.json'), true);
        foreach ($this->plugins as $plugin) {
            $json['require']['wpackagist-plugin/' . $plugin] = '@stable';
        }
        file_put_contents(getcwd() . '/tmp-trellis-helper/bedrock/composer.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this;
    }

    private function createRepo(InputInterface $input, OutputInterface $output)
    {
        $dir   = getcwd();
        $theme = $this->theme ? $this->theme : 'no theme';
        $output->writeln('<info>Create git repo on bitbucket with project</info>');

        chdir(getcwd() . '/tmp-trellis-helper/bedrock');

        $helper           = $this->getHelper('question');
        $usernameQuestion = new Question('Please enter your bitbucket username: ');
        $usernameQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('username cannot be blank');
            }
            return $answer;
        });

        $username = $helper->ask($input, $output, $usernameQuestion);

        $passQuestion = new Question('Please enter your bitbucket password: ');
        $passQuestion->setHidden(true);
        $passQuestion->setHiddenFallback(false);
        $passQuestion->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('password cannot be blank');
            }
            return $answer;
        });

        $password = $helper->ask($input, $output, $passQuestion);
        $client   = new Client();
        try {
            $response = $client->request('POST', 'https://api.bitbucket.org/2.0/repositories/19ideas/' . $this->domain, [
                'auth' => [$username, $password],
            ]);
        } catch (ClientException $e) {
            $output->writeln('<error>Unauthorized</error>');
            $output->writeln('<comment>Let\'s try that again</comment>');
            chdir($dir);
            return $this->createRepo($input, $output);
        }

        shell_exec('git init');
        shell_exec('git remote add origin git@bitbucket.org:19ideas/' . $this->domain . '.git');
        shell_exec('git add --all');
        shell_exec('git commit -m "Initialize theme with bedrock, ' . $theme . '"');
        shell_exec('git push -u origin master');
        shell_exec('git checkout -b production');
        shell_exec('git push -u origin production');
        shell_exec('git checkout -b dev');
        shell_exec('git push -u origin dev');

        chdir($dir);

        return $this;
    }

    private function addProjectToComposer(OutputInterface $output)
    {
        $output->writeln('<info>Add project to composer.json</info>');
        $json = json_decode(file_get_contents(getcwd() . '/composer.json'), true);

        $repo = [
            'type'    => 'package',
            'package' => [
                'name'    => '19ideas/' . $this->domain,
                'version' => 'dev-dev',
                'source'  => [
                    'url'       => 'git@bitbucket.org:19ideas/' . $this->domain . '.git',
                    'type'      => 'git',
                    'reference' => 'dev',
                ],
            ],
        ];

        array_push($json['repositories'], $repo);

        usort($json['repositories'], function ($a, $b) {
            return strcmp($a['package']['name'], $b['package']['name']);
        });
        $json['require']['19ideas/' . $this->domain] = 'dev-dev';
        ksort($json['require']);
        file_put_contents(getcwd() . '/composer.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $output->writeln('<info>Run composer update</info>');
        shell_exec('composer update');
        return $this;
    }

    private function setupProductionDatabaseVars($output)
    {
        $output->writeln('<info>Setting up production database variables</info>');
        $dir = getcwd();

        chdir(getcwd() . '/trellis');
        shell_exec('ansible-vault decrypt group_vars/production/vault.yml');
        
        $productionVault = Yaml::parse(file_get_contents(getcwd() . '/group_vars/production/vault.yml'));

        $dbString = str_replace(['-', '.'], '_', $this->domain);
        $this->productionVars['root_password'] = $productionVault['vault_mysql_root_password'];
        $this->productionVars['host'] = $productionVault['vault_mysql_production_host'];
        $this->productionVars['db_name'] = $dbString . '_production';
        $this->productionVars['db_user'] = $dbString;
        $this->productionVars['db_user_pass'] = $this->generateRandomString(10);

        chdir($dir);

        return $this;
    }

    private function addProjectToTrellis(OutputInterface $output)
    {
        $output->writeln('<info>Add project to trellis configs</info>');
        $dir = getcwd();

        chdir(getcwd() . '/trellis');
        shell_exec('ansible-vault decrypt group_vars/development/vault.yml group_vars/staging/vault.yml group_vars/production/vault.yml');
        $templates = [
            'development' => [
                'wordpress_sites'       => [
                    'site_hosts'  => [
                        [
                            'canonical' => $this->name . '.dev',
                            'redirects' => [
                                'www.' . $this->name . '.dev',
                            ],
                        ],
                    ],
                    'local_path'  => '../sites/' . $this->domain,
                    'admin_email' => 'admin@' . $this->name . '.dev',
                    'multisite'   => [
                        'enabled' => false,
                    ],
                    'ssl'         => [
                        'enabled'  => false,
                        'provider' => 'self-signed',
                    ],
                    'cache'       => [
                        'enabled' => false,
                    ],
                ],
                'vault_wordpress_sites' => [
                    'admin_password' => 'admin',
                    'env'            => [
                        'db_password' => $this->generateRandomString(10),
                    ],
                ],
            ],
            'staging'     => [
                'wordpress_sites'       => [
                    'site_hosts' => [
                        [
                            'canonical' => $this->name . '.19ideas.com',
                        ],
                    ],
                    'local_path' => '../sites/' . $this->domain,
                    'repo'       => 'git@bitbucket.org:19ideas/' . $this->domain . '.git',
                    'branch'     => 'dev',
                    'multisite'  => [
                        'enabled' => false,
                    ],
                    'ssl'        => [
                        'enabled'  => true,
                        'provider' => 'manual',
                        'cert'     => '~/.ssl/19ideas.com.crt',
                        'key'      => '~/.ssl/19ideas.com.private-key.pem',
                    ],
                    'cache'      => [
                        'enabled' => false,
                    ],
                    'theme'      => [
                        'version' => $this->theme,
                        'folder'  => $this->name . '-theme',
                    ],
                ],
                'vault_wordpress_sites' => [
                    'env' => [
                        'db_password'      => $this->generateRandomString(10),
                        'auth_key'         => $this->generateRandomString(55),
                        'secure_auth_key'  => $this->generateRandomString(55),
                        'logged_in_key'    => $this->generateRandomString(55),
                        'nonce_key'        => $this->generateRandomString(55),
                        'auth_salt'        => $this->generateRandomString(55),
                        'secure_auth_salt' => $this->generateRandomString(55),
                        'logged_in_salt'   => $this->generateRandomString(55),
                        'nonce_salt'       => $this->generateRandomString(55),
                    ],
                ],
            ],
            'production'  => [
                'wordpress_sites'       => [
                    'site_hosts' => [
                        [
                            'canonical' => $this->domain,
                            'redirects' => [
                                'www.' . $this->domain,
                            ],
                        ],
                    ],
                    'local_path' => '../sites/' . $this->domain,
                    'repo'       => 'git@bitbucket.org:19ideas/' . $this->domain . '.git',
                    'branch'     => 'production',
                    'multisite'  => [
                        'enabled' => false,
                    ],
                    'ssl'        => [
                        'enabled'                 => false,
                        'provider'                => 'letsencrypt',
                        'hsts_include_subdomains' => false,
                    ],
                    'cache'      => [
                        'enabled' => false,
                    ],
                    'theme'      => [
                        'version' => $this->theme,
                        'folder'  => $this->name . '-theme',
                    ],
                ],
                'vault_wordpress_sites' => [
                    'env' => [
                        'db_password'      => $this->productionVars['db_user_pass'],
                        'db_host' => $this->productionVars['host'],
                        'db_user' => $this->productionVars['db_user'],
                        'db_name' => $this->productionVars['db_name'],
                        'auth_key'         => $this->generateRandomString(55),
                        'secure_auth_key'  => $this->generateRandomString(55),
                        'logged_in_key'    => $this->generateRandomString(55),
                        'nonce_key'        => $this->generateRandomString(55),
                        'auth_salt'        => $this->generateRandomString(55),
                        'secure_auth_salt' => $this->generateRandomString(55),
                        'logged_in_salt'   => $this->generateRandomString(55),
                        'nonce_salt'       => $this->generateRandomString(55),
                    ],
                ],
            ],
        ];

        //development
        $developmentWordpressSites                                   = Yaml::parse(file_get_contents(getcwd() . '/group_vars/development/wordpress_sites.yml'));
        $developmentWordpressSites['wordpress_sites'][$this->domain] = $templates['development']['wordpress_sites'];
        ksort($developmentWordpressSites['wordpress_sites']);
        file_put_contents(getcwd() . '/group_vars/development/wordpress_sites.yml', Yaml::dump($developmentWordpressSites, 10, 2));

        $developmentVault                                         = Yaml::parse(file_get_contents(getcwd() . '/group_vars/development/vault.yml'));
        $developmentVault['vault_wordpress_sites'][$this->domain] = $templates['development']['vault_wordpress_sites'];
        ksort($developmentVault['vault_wordpress_sites']);
        file_put_contents(getcwd() . '/group_vars/development/vault.yml', Yaml::dump($developmentVault, 10, 2));

        //staging
        $stagingWordpressSites                                   = Yaml::parse(file_get_contents(getcwd() . '/group_vars/staging/wordpress_sites.yml'));
        $stagingWordpressSites['wordpress_sites'][$this->domain] = $templates['staging']['wordpress_sites'];
        ksort($stagingWordpressSites['wordpress_sites']);
        file_put_contents(getcwd() . '/group_vars/staging/wordpress_sites.yml', Yaml::dump($stagingWordpressSites, 10, 2));

        $stagingVault                                         = Yaml::parse(file_get_contents(getcwd() . '/group_vars/staging/vault.yml'));
        $stagingVault['vault_wordpress_sites'][$this->domain] = $templates['staging']['vault_wordpress_sites'];
        ksort($stagingVault['vault_wordpress_sites']);
        file_put_contents(getcwd() . '/group_vars/staging/vault.yml', Yaml::dump($stagingVault, 10, 2));

        //production
        $productionWordpressSites                                   = Yaml::parse(file_get_contents(getcwd() . '/group_vars/production/wordpress_sites.yml'));
        $productionWordpressSites['wordpress_sites'][$this->domain] = $templates['production']['wordpress_sites'];
        ksort($productionWordpressSites['wordpress_sites']);
        file_put_contents(getcwd() . '/group_vars/production/wordpress_sites.yml', Yaml::dump($productionWordpressSites, 10, 2));

        $productionVault                                         = Yaml::parse(file_get_contents(getcwd() . '/group_vars/production/vault.yml'));
        $productionVault['vault_wordpress_sites'][$this->domain] = $templates['production']['vault_wordpress_sites'];
        ksort($productionVault['vault_wordpress_sites']);
        file_put_contents(getcwd() . '/group_vars/production/vault.yml', Yaml::dump($productionVault, 10, 2));

        shell_exec('ansible-vault encrypt group_vars/development/vault.yml group_vars/staging/vault.yml group_vars/production/vault.yml');

        chdir($dir);
        
        return $this;
    }
    
    private function createProductionDatabase($output)
    {
        $output->writeln('<info>Create production database on RDS</info>');
        $mysqlPW = $this->productionVars['root_password'];
        $mysqlHost = $this->productionVars['host'];
        $dbName = $this->productionVars['db_name'];
        $dbUser = $this->productionVars['db_user'];
        $dbPass = $this->productionVars['db_user_pass'];
        shell_exec("mysql -u root -p${mysqlPW} -h ${mysqlHost} --execute='create database ${dbName};'");
        
        shell_exec("mysql -u root -p${mysqlPW} -h ${mysqlHost} --execute='create user \"${dbUser}\"@\"%\" identified by \"${dbPass}\"'");
        
        shell_exec("mysql -u root -p${mysqlPW} -h ${mysqlHost} --execute='grant all privileges on ${dbName} . * to \"${dbUser}\"@\"%\"'");
        
        return $this;
    }

    private function postConfig(OutputInterface $output)
    {
        $dir = getcwd();
        chdir(getcwd() . '/trellis');

        $output->writeln('<info>Running post config commands</info>');

        $output->writeln('<info>Run vagrant reload</info>');
        system('vagrant reload');

        $output->writeln('<info>Run vagrant provision</info>');
        system('vagrant provision');

        $output->writeln('<info>Activate all installed plugins</info>');
        system('vagrant ssh -- -t wp plugin activate --all --path=/srv/www/' . $this->domain . '/current');

        chdir($dir);

        return $this;
    }

    private function downloadArchive($url, $name, OutputInterface $output)
    {
        $output->writeln('<info>Downloading ' . $this->theme . ' from ' . $url . '</info>');
        $client   = new Client();
        $response = $client->get($url)->getBody();
        file_put_contents(getcwd() . '/tmp-trellis-helper/' . $name, $response);
        return getcwd() . '/tmp-trellis-helper/' . $name;
    }

    private function extractArchive($file)
    {
        $archive = new ZipArchive;

        $archive->open($file);
        $archive->extractTo(getcwd() . '/tmp-trellis-helper/bedrock/web/app/themes');
        $archive->close();

        return $this;
    }

    private function generateRandomString($length)
    {
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }
        return $string;
    }
}
