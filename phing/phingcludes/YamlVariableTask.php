<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlVariableTask
 *
 * This task allows YAML variables from YAML files to be output into Phing
 * properties.
 */
class YamlVariableTask extends Task {

  const FORMAT_LIST = 'list';

  /**
   * The YAML file from which to read the variable.
   * @var string
   */
  protected $file = NULL;


  /**
   * The YAML variable name. This may be a nested array or object.
   * @var string
   */
  protected $variable = NULL;

  /**
   * Nested property of $this->variable.
   * @var string
   */
  protected $variableProperty = NULL;

  protected $format = NULL;

  protected $dummy = NULL;

  /**
   * Property name to set with output value.
   * @var string
   */
  private $outputProperty;

  public function init() {
  }

  public function main() {

    $parsed = Yaml::parse(file_get_contents($this->file));

    switch ($this->format) {
      case self::FORMAT_LIST:
        $list = array();
        foreach ($parsed[$this->variable] as $item) {
          $nested = '';
          $cur = &$item;
          // Drill down to the requested nested key, allowing
          // nested keys to be passed in separated in dot syntax.
          foreach (explode('.', $this->variableProperty) as $key) {

            if (array_key_exists($key, $cur)) {
              $cur = $cur[$key];
            }
            else {
              throw new BuildException("The key '" . $key . "' could not be located in " . $this->variable);
            }

            // Continue drilling down until we're no longer
            // operating on an array.
            // @todo throw an error if this isn't the last iteration?
            if (!is_array($cur)) {
              $nested = $cur;
            }
          }

          // If there were not any nested properties,
          // simply add item's value, otherwise add the
          // nested property.
          $list[] = empty($nested) ? $item : $nested;
        }

        $value = implode(',', $list);
        break;

      default:
        $value = $parsed[$this->variable];
    }

    if (NULL !== $this->outputProperty) {
      $this->project->setProperty($this->outputProperty, $value);
    }

  }

  /**
   * Sets $this->file property.
   *
   * @param string $file
   *   The PHP file from which to read the variable.
   */
  public function setFile($file) {
    $this->file = $file;
  }

  /**
   * Sets $this->variable
   *
   * @param string $variable
   *   The PHP variable name. This may be a nested array.
   */
  public function setVariable($variable) {
    $this->variable = $variable;
  }

  public function setVariableProperty($property) {
    $this->variableProperty = $property;
  }

  /**
   * @remove
   */
  public function setDummy($dummy) {
    $this->dummy = $dummy;
  }

  /**
   * Sets $this->format property.
   *
   * @param string $format
   *   The user-defined output format for $this->outputProperty.
   */
  public function setFormat($format) {
    $this->format = $format;
  }

  /**
   * Sets $this->outputProperty property.
   *
   * @param string $prop
   *   The name of the Phing property whose value to set.
   */
  public function setOutputProperty($prop) {
    $this->outputProperty = $prop;
  }
}
