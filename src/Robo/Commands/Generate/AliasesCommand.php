<?php

namespace Acquia\Blt\Robo\Commands\Generate;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Cloud\Api\CloudApiClient;
use function file_put_contents;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Defines commands in the "generate:aliases" namespace.
 */
class AliasesCommand extends BltTasks {

  /**
   * @var string
   */
  protected $drushAliasDir;

  /** @var \Acquia\Cloud\Api\CloudApiClient*/
  protected $cloudApiClient;

  /**
   * @var string
   */
  protected $cloudConfDir;

  /**
   * @var string
   */
  protected $cloudConfFileName;

  /**
   * @var string
   */
  protected $cloudConfFilePath;

  /**
   * @var array
   */
  protected $cloudApiConfig;

  /** @var \Symfony\Component\Filesystem\Filesystem*/
  protected $fs;

  /** @var \Symfony\Component\Console\Helper\QuestionHelper*/
  protected $questionHelper;

  /**
   * @var \Symfony\Component\Console\Helper\FormatterHelper
   */
  protected $formatter;

  /**
   * Generates new Acquia site aliases for Drush.
   *
   * @command generate:aliases:acquia
   *
   */
  public function generateAliasesAcquia() {
    $continue = $this->confirm("This will overwrite existing drush aliases. Do you want to continue?");
    if (!$continue) {
      return 1;
    }

    $this->questionHelper = $this->getHelper('question');
    $this->formatter = $this->getHelper('formatter');
    $this->fs = new Filesystem();
    $this->cloudConfDir = $_SERVER['HOME'] . '/.acquia';
    $this->drushAliasDir = $_SERVER['HOME'] . '/.drush';
    $this->cloudConfFileName = 'cloudapi.conf';
    $this->cloudConfFilePath = $this->cloudConfDir . '/' . $this->cloudConfFileName;

    $this->cloudApiConfig = $this->loadCloudApiConfig();
    $this->setCloudApiClient($this->cloudApiConfig['email'], $this->cloudApiConfig['key']);
    $this->say("<info>Gathering sites list from Acquia Cloud.</info>");
    $sites = (array) $this->cloudApiClient->sites();
    $sitesCount = count($sites);
    $this->progressBar = new ProgressBar($this->output(), $sitesCount);
    $style = new OutputFormatterStyle('white', 'blue');
    $this->output()->getFormatter()->setStyle('status', $style);
    $this->progressBar->setFormat("<status> %current%/%max% subscriptions [%bar%] %percent:3s%% \n %message%</status>");
    $this->progressBar->setMessage('Starting Aliases sync...');
    $this->say(
      "<info>Found " . $sitesCount . " subscription(s). Gathering information about each.</info>\n"
    );
    $errors = [];
    $this->progressBar->setRedrawFrequency(0.1);
    foreach ($sites as $site) {
      $this->progressBar->setMessage('Syncing: ' . $site);
      try {
        $this->getSiteAliases($site, $errors);
      }
      catch (\Exception $e) {
        $errors[] = "Could not fetch alias data for $site. Error: " . $e->getMessage();
      }
      $this->progressBar->advance();
    }
    $this->progressBar->setMessage("Syncing: complete. \n");
    $this->progressBar->clear();
    $this->progressBar->finish();
    if ($errors) {
      $formatter = $this->getHelper('formatter');
      $formattedBlock = $formatter->formatBlock($errors, 'error');
      $this->output()->writeln($formattedBlock);
    }
    $this->output->writeln("<info>Aliases were written to, type 'drush sa' to see them.</info>");
  }

  /**
   * @return array
   */
  protected function loadCloudApiConfig() {
    if (!$config = $this->loadCloudApiConfigFile()) {
      $config = $this->askForCloudApiCredentials();
    }
    return $config;
  }

  /**
   * @return array
   */
  protected function loadCloudApiConfigFile() {
    $config_dirs = [
      $_SERVER['HOME'] . $this->cloudConfDir,
    ];
    $locator = new FileLocator($config_dirs);
    try {
      $file = $locator->locate($this->cloudConfFileName, NULL, TRUE);
      $loaderResolver = new LoaderResolver(array(new JsonFileLoader($locator)));
      $delegatingLoader = new DelegatingLoader($loaderResolver);
      $config = $delegatingLoader->load($file);
      return $config;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   *
   */
  protected function askForCloudApiCredentials() {
    $usernameQuestion = new Question('<question>Please enter your Acquia cloud email address:</question> ', '');
    $privateKeyQuestion = new Question('<question>Please enter your Acquia cloud private key:</question> ', '');
    $privateKeyQuestion->setHidden(TRUE);
    do {
      $email = $this->questionHelper->ask($this->input, $this->output, $usernameQuestion);
      $key = $this->questionHelper->ask($this->input, $this->output, $privateKeyQuestion);
      $this->setCloudApiClient($email, $key);
      $cloud_api_client = $this->getCloudApiClient();
    } while (!$cloud_api_client);
    $config = array(
      'email' => $email,
      'key' => $key,
    );
    $this->writeCloudApiConfig($config);
  }

  /**
   * @param $cloud_api_client
   *
   * @return string
   */
  protected function askWhichCloudSite($cloud_api_client) {
    $question = new ChoiceQuestion(
      '<question>Which site?</question>',
      $this->getSitesList($cloud_api_client)
    );
    $site_name = $this->questionHelper->ask($this->input, $this->output, $question);
    return $site_name;
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param Site $site
   */
  protected function askWhichCloudEnvironment($cloud_api_client, $site) {
    $environments = $this->getEnvironmentsList($cloud_api_client, $site);
    $question = new ChoiceQuestion(
      '<question>Which environment?</question>',
      (array) $environments
    );
    $env = $this->questionHelper->ask($this->input, $this->output, $question);
    return $env;
  }

  /**
   * @param $config
   */
  protected function writeCloudApiConfig($config) {
    file_put_contents($this->cloudConfFilePath, json_encode($config));
    $this->output->writeln("<info>Credentials were written to {$this->cloudConfFilePath}.</info>");
  }

  /**
   * @return mixed
   */
  protected function getCloudApiConfig() {
    return $this->cloudApiConfig;
  }

  protected function setCloudApiClient($username, $password) {
    try {
      $cloudapi = CloudApiClient::factory(array(
        'username' => $username,
        'password' => $password,
      ));
      // We must call some method on the client to test authentication.
      $cloudapi->sites();
      $this->cloudApiClient = $cloudapi;
      return $cloudapi;
    }
    catch (\Exception $e) {
      // @todo this is being thrown after first auth. still works? check out.
      $this->output->writeln("<error>Failed to authenticate with Acquia Cloud API.</error>");
      if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
        $this->output->writeln('Exception was thrown: ' . $e->getMessage());
      }
      return NULL;
    }
  }

  /**
   * @return \Acquia\Cloud\Api\CloudApiClient
   */
  protected function getCloudApiClient() {
    return $this->cloudApiClient;
  }

  /**
   * @param $dir_name
   *
   * @return int
   */
  protected function checkDestinationDir($dir_name) {
    $destination_dir = getcwd() . '/' . $dir_name;
    if ($this->fs->exists($destination_dir)) {
      $this->output->writeln("<comment>Uh oh. The destination directory already exists.</comment>");
      $question = new ConfirmationQuestion("<comment>Delete $destination_dir?</comment> ", FALSE);
      $delete_dir = $this->questionHelper->ask($this->input, $this->output, $question);
      if ($delete_dir) {
        if ($this->fs->exists($destination_dir . '/.vagrant')) {
          $this->output->writeln('');
          $this->output->writeln("<comment>One more thing, it looks like there's a vagrant machine in the destination directory.</comment>");
          $question = new ConfirmationQuestion("<comment>Destroy the vagrant machine in $destination_dir?</comment> ", FALSE);
          $vagrant_destroy = $this->questionHelper->ask($this->input, $this->output, $question);
          if ($vagrant_destroy) {
            $this->executeCommand('vagrant destroy --force', $destination_dir);
          }
        }
        // @todo recursively chmod all files in docroot/sites/default.
        $this->fs->chmod($destination_dir . '/docroot/sites/default/default.settings.php', 777);
        $this->fs->remove($destination_dir);
      }
      else {
        $this->output->writeln(
          "<comment>Please choose a different machine name for your project, or change directories.</comment>"
              );
        return 1;
      }
    }
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param $site_id
   *
   * @return \Acquia\Cloud\Api\Response\Site
   */
  protected function getSite(CloudApiClient $cloud_api_client, $site_id) {
    return $cloud_api_client->site($site_id);
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   *
   * @return array
   */
  protected function getSites(CloudApiClient $cloud_api_client) {
    $sites = $cloud_api_client->sites();
    $sites_filtered = [];
    foreach ($sites as $key => $site) {
      $label = $this->getSiteLabel($site);
      if ($label !== '*') {
        $sites_filtered[(string) $site] = $site;
      }
    }
    return $sites_filtered;
  }

  /**
   * @param $site
   *
   * @return mixed
   */
  protected function getSiteLabel($site) {
    $site_slug = (string) $site;
    $site_split = explode(':', $site_slug);
    return $site_split[1];
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   *
   * @return array
   */
  protected function getSitesList(CloudApiClient $cloud_api_client) {
    $site_list = [];
    $sites = $this->getSites($cloud_api_client);
    foreach ($sites as $site) {
      $site_list[] = $this->getSiteLabel($site);
    }
    sort($site_list, SORT_NATURAL | SORT_FLAG_CASE);
    return $site_list;
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param $label
   *
   * @return \Acquia\Cloud\Api\Response\Site|null
   */
  protected function getSiteByLabel(CloudApiClient $cloud_api_client, $label) {
    $sites = $this->getSites($cloud_api_client);
    foreach ($sites as $site_id) {
      if ($this->getSiteLabel($site_id) == $label) {
        $site = $this->getSite($cloud_api_client, $site_id);
        return $site;
      }
    }
    return NULL;
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param $site
   *
   * @return array
   */
  protected function getEnvironmentsList(CloudApiClient $cloud_api_client, $site) {
    $environments = $cloud_api_client->environments($site);
    $environments_list = [];
    foreach ($environments as $environment) {
      $environments_list[] = $environment->name();
    }
    return $environments_list;
  }

  /**
   * @param $site SiteNames[]
   */
  protected function getSiteAliases($site, &$errors) {
    // Skip AC trex sites because the api breaks on them.
    $skip_site = FALSE;
    if (strpos($site, 'trex') !== FALSE
      || strpos($site, ':*') !== FALSE) {
      $skip_site = TRUE;
    }
    if (!$skip_site) {
      // Gather our environments.
      $environments = $this->cloudApiClient->environments($site);
      // Lets split the site name in the format ac-realm:ac-site.
      $site_split = explode(':', $site);
      $siteRealm = $site_split[0];
      $siteID = $site_split[1];
      // Loop over all environments.
      foreach ($environments as $env) {
        // Build our variables in case API changes.
        $envName = $env->name();
        $uri = $env->defaultDomain();
        $remoteHost = $env->sshHost();
        $remoteUser = $env['unix_username'];
        $docroot = '/var/www/html/' . $siteID . '.' . $envName . '/docroot';
        $aliases[$envName] = array(
          'env-name' => $envName,
          'root' => $docroot,
          'ac-site' => $siteID,
          'ac-env' => $envName,
          'ac-realm' => $siteRealm,
          'uri' => $uri,
          'remote-host' => $remoteHost,
          'remote-user' => $remoteUser,
        );
      }
      if ($siteRealm == 'enterprise-g1') {
        $acsf_site_url = 'https://www.' . $siteID . '.acsitefactory.com';
        if ($this->checkForACSFCredentials($siteID)) {
          // @TODO: Ask the user if they want to update the credentials
          $sites = $this->getACSFAliases($siteID, $acsf_site_url, $errors);
          if (!empty($sites)) {
            $prod_group = array();
            $stage_group = array();
            $dev_group = array();
            foreach ($sites as $site) {
              // Configure prod aliases.
              $site_alias = $site->site . '.prod';
              $this->generateACSFAliases($aliases, $siteID, $site_alias, $site->domain, '.01live');
              // Configure prod group.
              $prod_group[] = '@' . $site_alias;
              // Configure stage aliases.
              $site_alias = $site->site . '.stage';
              $this->generateACSFAliases($aliases, $siteID, $site_alias, $site->domain, '.01test');
              // Configure stage group.
              $stage_group[] = '@' . $site_alias;
              // Configure dev aliases.
              $site_alias = $site->site . '.dev';
              $this->generateACSFAliases($aliases, $siteID, $site_alias, $site->domain, '.01dev');
              // Configure dev group.
              $dev_group[] = '@' . $site_alias;
            }
            $aliases['allprod'] = array(
              'site' => $site->site,
              'aliases' => $prod_group,
              'env' => 'prod',
              'group' => TRUE,
            );
            $aliases['allstage'] = array(
              'site' => $site->site,
              'aliases' => $stage_group,
              'env' => 'stage',
              'group' => TRUE,
            );
            $aliases['alldev'] = array(
              'site' => $site->site,
              'aliases' => $dev_group,
              'env' => 'dev',
              'group' => TRUE,
            );
          }
        }
        else {
          $this->progressBar->clear();
          $question = new ConfirmationQuestion(
            "<comment>Found an Acquia Cloud Site Factory instance named <info>" . $siteID . "</info>.\nTo setup aliases for this instance you will need an instance-specific API key found at <info>" . $acsf_site_url . "</info>. \nDo you want to download aliases for this instance? [y/n]:</comment> ",
            FALSE
                  );
          $continue = $this->questionHelper->ask($this->input, $this->output, $question);
          if ($continue) {
            $this->askForACSFCredentials($siteID);
            $this->getACSFAliases($siteID, $acsf_site_url);
          }
          else {
            $config = $this->cloudApiConfig;
            $acsfConfig = array(
              "$siteID" => array(
                'username' => '',
                'apikey' => '',
                'enabled' => FALSE,
              ),
            );
            // @todo this fails when "n" is selected
            $config = array_merge_recursive($config, $acsfConfig);
            $this->writeCloudApiConfig($config);
          }
        }
      }
      $this->writeSiteAliases($siteID, $aliases);
    }
  }

  protected function generateACSFAliases(&$aliases, $siteID, $site_alias, $domain, $env) {
    $parent = '@' . $siteID . $env;
    switch ($env) {
      case '.01dev':
        $domain = str_replace($siteID, 'dev-' . $siteID, $domain);
        break;

      case '.01test':
        $domain = str_replace($siteID, 'test-' . $siteID, $domain);
        break;
    }
    $aliases[$site_alias]['uri'] = $domain;
    $aliases[$site_alias]['parent'] = $parent;
    $aliases[$site_alias]['site'] = $site_alias;
    $aliases[$site_alias]['env'] = $env;
  }

  protected function writeSiteAliases($site_id, $aliases) {
    // Load twig template.
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../../Resources/templates');
    $twig = new Twig_Environment($loader);
    // Render our aliases.
    $aliasesRender = $twig->render('aliases.php.twig', array('aliases' => $aliases));
    $aliasesFileName = $this->drushAliasDir . '/' . $site_id . '.aliases.drushrc.php';
    $writable = (is_writable($aliasesFileName)) ? TRUE : chmod($aliasesFileName, 0755);
    // Write to file.
    file_put_contents($aliasesFileName, $aliasesRender);
  }

  protected function getACSFAliases($siteID, $acsf_site_url, &$errors) {
    $username = $this->cloudApiConfig[$siteID]['username'];
    $apikey = $this->cloudApiConfig[$siteID]['apikey'];
    $creds = base64_encode($username . ':' . $apikey);
    try {
      $sitesList = $this->curlCallToURL($acsf_site_url, $creds);
      $count = $sitesList['count'];
      if ($count > 100) {
        $numberOfPages = $count / 100;
        for ($i = 2; $i <= ceil($numberOfPages); $i++) {
          $sitesList = array_merge_recursive($sitesList, $this->curlCallToURL($acsf_site_url, $creds, $i));
        }
      }
      return $sitesList['sites'];
    }
    catch (\Exception $e) {
      $errors[] = "Failed to fetch " . $siteID . " aliases: " . $e->getMessage();
      return FALSE;
    }
  }

  protected function checkForACSFCredentials($siteId) {
    if (isset($this->cloudApiConfig[$siteId])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   *
   */
  protected function askForACSFCredentials($siteId) {
    $usernameQuestion = new Question("<question>Please enter your ACSF username for $siteId</question>: ", '');
    $privateKeyQuestion = new Question("<question>Please enter your ACSF API key for $siteId</question>: ", '');
    $privateKeyQuestion->setHidden(TRUE);
    $username = $this->questionHelper->ask($this->input, $this->output, $usernameQuestion);
    $apikey = $this->questionHelper->ask($this->input, $this->output, $privateKeyQuestion);
    $config = $this->cloudApiConfig;
    $acsfConfig = array(
      "$siteId" => array(
        'username' => $username,
        'apikey' => $apikey,
        'enabled' => TRUE,
      ),
    );
    $this->cloudApiConfig = array_merge_recursive($config, $acsfConfig);
    $this->writeCloudApiConfig($this->cloudApiConfig);
  }

  protected function curlCallToURL($url, $creds, $page = 1, $limit = 100) {
    $full_url = $url . '/api/v1/sites?limit=' . $limit . '&page=' . $page;
    // Get cURL resource.
    $ch = curl_init();
    // Set url.
    curl_setopt($ch, CURLOPT_URL, $full_url);
    // Set method.
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    // Set options.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // Set headers.
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Basic " . $creds,
    ]);
    // Send the request & save response to $resp.
    $resp = curl_exec($ch);
    if (!$resp) {
      die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
    }
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 403) {
      throw new \Exception('API Authorization failed.');
    }
    // Close request to clear up some resources.
    curl_close($ch);
    return (array) json_decode($resp);
  }

}
