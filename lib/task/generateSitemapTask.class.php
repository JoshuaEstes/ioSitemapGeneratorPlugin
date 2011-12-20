<?php

/**
 * 
 *
 * @author
 * @copyright
 * @package    ioSitemapGeneratorPlugin
 * @subpackage
 * @version
 *
 */
class generateSitemapTask extends sfBaseTask
{

  /**
   *
   */
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'doctrine'),
      new sfCommandOption('sf_host', null, sfCommandOption::PARAMETER_REQUIRED, 'The host, example: http://www.example.com'),
    ));

    $this->namespace = 'generate';
    $this->name = 'sitemap';
    $this->briefDescription = 'Generates a sitemap';
    $this->detailedDescription = <<<EOF
The [generate:sitemap|INFO] task does things.
Call it with:

  [php symfony generate:sitemap|INFO]
EOF;
  }

  /**
   *
   * @param array $arguments
   * @param array $options
   */
  protected function execute($arguments = array(), $options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);
    $connection = $databaseManager->getDatabase($options['connection'])->getConnection();

    // If the user did not pass the option sf_host then grab it from the config
    if (null === $options['sf_host'])
    {
      $options['sf_host'] = sfConfig::get('app_ioSitemapGenerator_sf_host');
    }

    // Create the xml sitemap
    $doc = new DOMDocument('1.0', 'UTF-8');
    $urlset = $doc->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $doc->appendChild($urlset);

    // Grab a list of models we want to generate a sitemap for
    $models = sfConfig::get('app_ioSitemapGenerator_models');

    /**
     * $urlNB is only used to count the number of links that we place in the
     * sitemap.xml file
     */
    $urlNB = 0;
    foreach ($models as $model)
    {
      /* @var $modelTable Doctrine_Table */
      $modelTable = Doctrine_Core::getTable($model['model']);
      /* @var $collection Doctrine_Collection */
      $collection = $modelTable->findAll();
      foreach ($collection as $record)
      {
        /* @var $record Doctrine_Record */
        $url = $doc->createElement('url');
        $uri = $options['sf_host'] . $this->getRouting()->generate($model['route'], $record);
        $this->logSection('uri+', $uri);
        $url->appendChild($doc->createElement('loc', $uri));

        if (isset($model['changefreq']))
        {
          $this->logSection('changefreq+', $model['changefreq'], null, 'COMMENT');
          $url->appendChild($doc->createElement('changefreq', $model['changefreq']));
        }

        if (isset($model['priority']))
        {
          $this->logSection('priority+', $model['priority'], null, 'COMMENT');
          $url->appendChild($doc->createElement('priority', $model['priority']));
        }

        if ((!isset($model['include_lastmod']) || !$model['include_lastmod']) && $modelTable->hasColumn('updated_at'))
        {
          $this->logSection('lastmod+', $record->updated_at, null, 'COMMENT');
          $url->appendChild($doc->createElement('lastmod', $record->updated_at));
        }

        $urlset->appendChild($url);
        $urlNB++;
      }
    }

    // Grab the urls
    $urls = sfConfig::get('app_ioSitemapGenerator_urls');
    foreach ($urls as $record)
    {
      $url = $doc->createElement('url');
      $uri = $options['sf_host'] . $this->getRouting()->generate($record['route']);
      $this->logSection('uri+', $uri);
      $url->appendChild($doc->createElement('loc', $uri));

      if (isset($record['changefreq']))
      {
        $this->logSection('changefreq+', $record['changefreq'], null, 'COMMENT');
        $url->appendChild($doc->createElement('changefreq', $record['changefreq']));
      }

      if (isset($record['priority']))
      {
        $this->logSection('priority+', $record['priority'], null, 'COMMENT');
        $url->appendChild($doc->createElement('priority', $record['priority']));
      }

      $urlset->appendChild($url);
      $urlNB++;
    }


    $this->logSection('saving', 'Saving sitemap.xml');
    $filename = sfConfig::get('sf_web_dir') . '/sitemap.xml';
    $this->getFilesystem()->touch($filename);
    file_put_contents($filename, $doc->saveXML());
    $this->logBlock(sprintf('Total URLs: %d', $urlNB), 'COMMENT_LARGE');
  }

}
