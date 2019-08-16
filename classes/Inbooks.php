<?php


namespace Neurozone;

use DOMDocument;
use EPubDOMXPath;
use Exception;
use ZipArchive;

class Inbooks
{
    protected $file;
    protected $meta;
    protected $namespaces;
    protected $imagetoadd = '';
    private $zip;

    private $metaTitle;
    private $metaAuthor;
    private $metaDescription;
    private $metaSubjects;
    private $metaPublisher;
    private $metaCopyright;
    private $metaLanguage;
    private $metaISBN;
    private $metaUID;
    private $metaVersion;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file)
    {
        // open file
        $this->file = utf8_decode($file);

        $this->zip = new ZipArchive();
        if (!$this->zip->open($this->file))
        {
            throw new Exception('Failed to read epub file');
        }

        // read container data
        $data = $this->zip->getFromName('META-INF/container.xml');

        if ($data == false)
        {
            throw new Exception('Failed to access epub container data');
        }

        /*
        $xml = new DOMDocument();
        $xml->registerNodeClass('DOMElement', 'EPubDOMElement');
        $xml->loadXML($data);
        $xpath = new EPubDOMXPath($xml);
        $nodes = $xpath->query('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        $this->meta = $nodes->item(0)->attr('full-path');

        // load metadata
        $metadata = $this->zip->getFromName($this->meta);
        if (!$metadata)
        {
            throw new Exception('Failed to access epub metadata');
        }
        $this->xml = new DOMDocument();
        $this->xml->registerNodeClass('DOMElement', 'EPubDOMElement');
        $this->xml->loadXML($metadata);
        $this->xml->formatOutput = true;
        $this->xpath = new EPubDOMXPath($this->xml);

        $zip->close();
        */
    }

    /**
     * file name getter
     */
    public function filePath()
    {
        return $this->file;
    }

    public function getOpf()
    {
        // read container data
        $data = $this->zip->getFromName('OPS/content.opf');
        $package = simplexml_load_string($data);
        echo $package->metadata->children('dc', true)->title;
        return $data;
    }

    public function getSpine()
    {
        // read container data
        /*
         * <book category="COOKING">
                <title lang="en">Everyday Italian</title>
            $xml=simplexml_load_file("books.xml") or die("Error: Cannot create object");
    foreach($xml->children() as $books) {
        echo $books->title['lang'];
        echo "<br>";
}
          <spine toc="ncx">
            <itemref idref="ch1" />
         */
        $data = $this->zip->getFromName('OPS/content.opf');
        $package = simplexml_load_string($data);
        foreach($package->spine as $item)
        {

            foreach($item->itemref as $i )
            {
                $toc[] = $i['idref'] ;
            }

        }

        return $toc;
    }

    public function getMenu()
    {
        $data = $this->zip->getFromName('OPS/content.opf');
        $package = simplexml_load_string($data);
        $toc = $this->getSpine();
        foreach($toc as $t)
        {

            foreach($package->manifest as $item)
            {
                //$item->id['$t']

                foreach($item as $i )
                {
                    print_r($i);
                }
            }
        }
    }

    public function getAuthors()
    {
        // read current data
        $rolefix = false;
        $authors = array();
        $nodes = $this->xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if ($nodes->length == 0)
        {
            // no nodes where found, let's try again without role
            $nodes = $this->xpath->query('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach ($nodes as $node)
        {
            $name = $node->nodeValue;
            $as = $node->attr('opf:file-as');
            if (!$as)
            {
                $as = $name;
                $node->attr('opf:file-as', $as);
            }
            if ($rolefix)
            {
                $node->attr('opf:role', 'aut');
            }
            $authors[$as] = $name;
        }
        return $authors;
    }
}