<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

class QubitCsvTransform extends QubitFlatfileImport
{
  public
    $setupLogic,
    $transformLogic,
    $rowsPerFile = 1000;

  public function __construct($options = array())
  {
    if (
      !isset($options['skipOptionsAndEnvironmentCheck'])
      || $options['skipOptionsAndEnvironmentCheck'] == false
    )
    {
      $this->checkTaskOptionsAndEnvironment($options['options']);
    }

    // unset options not allowed in parent class
    unset($options['skipOptionsAndEnvironmentCheck']);
    if (isset($options['options']))
    {
      $cliOptions = $options['options'];
      unset($options['options']);
    }

    // call parent class constructor
    parent::__construct($options);

    if (isset($options['setupLogic']))
    {
      $this->setupLogic = $options['setupLogic'];
    }

    if (isset($options['transformLogic']))
    {
      $this->transformLogic = $options['transformLogic'];
    }

    if (isset($cliOptions)) {
      $this->status['finalOutputFile'] = $cliOptions['output-file'];
      $this->status['ignoreBadLod'] = $cliOptions['ignore-bad-lod'];
    }
    $this->status['headersWritten']  = false;

    // Load levels of description from database
    $criteria = new Criteria;
    $criteria->add(QubitTerm::TAXONOMY_ID, QubitTaxonomy::LEVEL_OF_DESCRIPTION_ID);
    $criteria->add(QubitTermI18n::CULTURE, 'en');
    $criteria->addJoin(QubitTerm::ID, QubitTermI18n::ID);
    $criteria->addAscendingOrderByColumn('lft');

    $this->levelsOfDescription = array();
    foreach (QubitTerm::get($criteria) as $term)
    {
      $this->levelsOfDescription[] = strtolower($term->name);
    }
  }

  protected function checkTaskOptionsAndEnvironment($options)
  {
    if (!$options['output-file'])
    {
      throw new sfException('You must specifiy the output-file option.');
    }

    if (getEnv('MYSQL_PASSWORD') === false)
    {
      throw new sfException('You must set the MYSQL_PASSWORD environmental variable. This script will use the "root" user and a database called "import".');
    }
  }

  function writeHeadersOnFirstPass()
  {
    // execute setup logic, if any
    if (isset($this->setupLogic))
    {
      $this->executeClosurePropertyIfSet('setupLogic');
    }

    if (!$this->status['headersWritten'])
    {
      fputcsv($this->status['outFh'], $this->columnNames);
      $this->status['headersWritten'] = true;
    }
  }

  function initializeMySQLtemp()
  {
    if (false === $password = getEnv('MYSQL_PASSWORD'))
    {
      throw new sfException('You must set the MYSQL_PASSWORD environmental variable. This script will use the "root" user and a database called "import".');
    }

    if (false === $link = mysqli_connect('localhost', 'root', $password, 'import'))
    {
      throw new sfException('MySQL connection failed. Make sure the MYSQL_PASSWORD environmental variable is set.');
    }

    $sql = "CREATE TABLE IF NOT EXISTS import_descriptions (
      id INT NOT NULL AUTO_INCREMENT,
      sortorder INT,
      data LONGTEXT,
      PRIMARY KEY (id)
    )";
    if (false === mysqli_query($link, $sql))
    {
      throw new sfException('MySQL create table failed.');
    }

    $sql = 'DELETE FROM import_descriptions';
    if (false === mysqli_query($link, $sql))
    {
      throw new sfException('MySQL delete from import_descriptions failed.');
    }
  }

  function addRowToMySQL($sortorder)
  {
    $sql = "INSERT INTO import_descriptions
        (sortorder, data)
        VALUES ('". mysqli_real_escape_string($sortorder) ."',
        '". mysqli_real_escape_string(serialize($this->status['row'])) ."')";

    $result = mysqli_query($sql);

    if (!$result)
    {
      throw new sfException('Failed to create MySQL DB row.');
    }
  }

  function numberedFilePathVariation($filename, $number)
  {
    $parts     = pathinfo($filename);
    $base      = $parts['filename'];
    $path      = $parts['dirname'];
    return $path .'/'. $base .'_'. $number .'.'. $parts['extension'];
  }

  function writeMySQLRowsToCsvFilePath($filepath)
  {
    $chunk = 0;
    $startFile = $this->numberedFilePathVariation($filepath, $chunk);
    $fhOut = fopen($startFile, 'w');

    if (!$fhOut) throw new sfException('Error writing to '. $startFile .'.');

    print "Writing to ". $startFile ."...\n";

    fputcsv($fhOut, $this->columnNames); // write headers

    // cycle through DB, sorted by sort, and write CSV file
    $sql = "SELECT data FROM import_descriptions ORDER BY sortorder";

    $result = mysqli_query($sql);

    $currentRow = 1;

    while($row = mysqli_fetch_assoc($result))
    {
      // if starting a new chunk, write CSV headers
      if (($currentRow % $this->rowsPerFile) == 0)
      {
        $chunk++;
        $chunkFilePath = $this->numberedFilePathVariation($filepath, $chunk);
        $fhOut = fopen($chunkFilePath, 'w');

        print "Writing to ". $chunkFilePath ."...\n";

        fputcsv($fhOut, $this->columnNames); // write headers
      }

      $data = unserialize($row['data']);

      // write to CSV out
      fputcsv($fhOut, $data);

      $currentRow++;
    }
  }

  function levelOfDescriptionToSortorder($level)
  {
    return array_search(strtolower($level), $this->levelsOfDescription);
  }
}
