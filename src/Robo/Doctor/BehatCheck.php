<?php

namespace Acquia\Blt\Robo\Doctor;

/**
 * BLT Doctor checks.
 */
class BehatCheck extends DoctorCheck {

  /**
   * Perform all checks.
   */
  public function performAllChecks() {
    $this->checkBehat();
  }

  /**
   * Checks Behat configuration in local.yml.
   */
  protected function checkBehat() {
    $this->checkLocalConfig();
    if ($this->behatLocalYmlExists()) {
      $behatDefaultLocalConfig = $this->getInspector()->getLocalBehatConfig()->export();
      $this->checkBaseUrl($behatDefaultLocalConfig);
    }
  }

  /**
   * Check base url.
   *
   * @param array $behatDefaultLocalConfig
   *   Default local config.
   */
  protected function checkBaseUrl(array $behatDefaultLocalConfig) {
    $behat_base_url = $behatDefaultLocalConfig['local']['extensions']['Behat\MinkExtension']['base_url'];
    if ($behat_base_url != $this->drushStatus['uri']) {
      $this->logProblem(__FUNCTION__ . ':uri', [
        "base_url in tests/behat/local.yml does not match the site URI.",
        "  Behat base_url is set to <comment>$behat_base_url</comment>.",
        "  Drush site URI is set to <comment>{$this->drushStatus['uri']}</comment>.",
      ], 'error');
    }
  }

  /**
   * Check local config.
   */
  protected function checkLocalConfig() {
    if (!$this->behatLocalYmlExists()) {
      $this->logProblem(__FUNCTION__ . ':exists', [
        "tests/behat/local.yml is missing!",
        "  Run `blt setup:behat` to generate it from example.local.yml.",
      ], 'error');
    }
  }

  /**
   * Behat local yml exists.
   *
   * @return bool
   *   Bool.
   */
  protected function behatLocalYmlExists() {
    return file_exists($this->getConfigValue('repo.root') . '/tests/behat/local.yml');
  }

}
