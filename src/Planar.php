<?php
namespace MoussaClarke;

use \SebastianBergmann\Diff\Differ;

/**
* A simple json flat file/nosql database
*
* 'Collection' and 'document' are used in the MongoDB sense
*
* @author Moussa Clarke
*/
class Planar
{
    /**
     * The data the collection contains
     *
     * @var array
     */
    protected $data;
    /**
     * The location of the json database folder
     * Can be set either by injecting into construct or over-riding in base extended class
     *
     * @var array
     */
    protected $datafolder = null;
    /**
     * The name of the collection/model, uses the extended class name
     *
     * @var string
     */
    protected $collectionname;
    /**
     * The location of the json database file
     *
     * @var string
     */
    protected $dbfile;
    /**
     * Does this collection persist to database?
     *
     * @var bool
     */
    protected $persists = true;
    /**
     * The schema for the collection
     *
     * @var array
     */
    protected $schema = [];

    /**
     * Construct the class
     * Location of data folder can be injected
     * The short name of the model class that extends this one will dictate the name of the json file
     *
     * @param string $datafolder
     *
     */
    public function __construct($datafolder = null)
    {
        // if $datafolder not set, check the class property in case over-ridden
        $datafolder = $datafolder ? $datafolder : $this->datafolder;

        // if still not set, throw an exception
        if (!$datafolder) {
            throw new \Exception('Planar datafolder not set.');
        }

        // if the folder doesn't exist yet, let's make it
        if (!file_exists($datafolder)) {
            mkdir($datafolder);
        }
        // get the short name via reflection as we are in a namespace
        $reflect              = new \ReflectionClass($this);
        $this->collectionname = $reflect->getShortName();
        $this->backupfolder   = $datafolder . '/backups';
        $this->dbfile         = $datafolder . '/' . $this->collectionname . '.json';
        if (file_exists($this->dbfile)) {
            $this->data = json_decode(file_get_contents($this->dbfile), true);
        } else {
            $this->data = [];
            $this->save();
        }

    }

    /**
     * Get the collection schema
     *
     * @return array
     */
    public function getSchema()
    {
        // return the schema, which might just be a blank array
        return $this->schema;
    }

    /**
     * Return an array of documents where property named $key has a particular $value
     * Case sensitive, false if nothing found
     *
     * @param string $key
     * @param string $value
     * @return array|false
     */
    public function find($key, $value)
    {
        // search the data for the value
        $found = [];
        foreach ($this->data as $item) {
            if ($item[$key] == $value) {
                $found[] = $item;
            }
        }
        return empty($found) ? false : $found;
    }

    /**
     * Return the first document where property named $key has a particular value
     * Case sensitive, false if nothing found
     *
     * @param string $key
     * @param string $value
     * @return array|false
     */
    public function first($key, $value)
    {
        // search the data for the value, break on find
        $found = false;
        foreach ($this->data as $item) {
            if ($item[$key] == $value) {
                $found = $item;
                break;
            }
        }
        return $found;
    }

    /**
     * Returns the whole collection, sorted by $sortby field
     *
     * @param string $sortby
     * @return array
     */
    public function all($sortby = null)
    {
        // returns the whole collection, sorted
        $data = $this->data;

        if ($sortby) {
            uasort($data, function ($a, $b) use ($sortby) {
                return $a[$sortby] > $b[$sortby];
            });
        }
        return $data;
    }

    /**
     * return an array of documents where any property contains $value
     * case insensitive
     *
     * @param string $value
     * @return array|false
     */
    public function search($value)
    {
        // use recursive find algo to find all instances
        $recursiveFind = function ($needle, $haystack) use (&$recursiveFind) {
            if (is_array($haystack)) {
                foreach ($haystack as $key => $itemvalue) {
                    if ($recursiveFind($needle, $itemvalue)) {
                        return true;
                        break;
                    };
                }
                return false;
            } elseif (strpos(strtolower($haystack), strtolower($needle)) !== false) {
                return true;
            } else {
                return false;
            }
        };

        $found = [];
        foreach ($this->data as $item) {
            foreach ($item as $key => $itemvalue) {
                if ($recursiveFind($value, $item[$key])) {
                    $found[] = $item;
                    break;
                }
            }
        }
        return empty($found) ? false : $found;
    }

    /**
     * Replace a document with $properties
     * 
     * @param string $id
     * @param array $properties
     * @return string|false
     */
    public function set($id, array $properties)
    {
        // replaces a document
        $oldversion = $this->first('id', $id);
        if (!$oldversion) {
            return false;
        }
        $properties['id']       = $id;
        $properties['modified'] = time();
        $properties['created']  = $oldversion['created'];
        $this->data[$id]        = $properties;
        $this->save($id);
        return $id;
    }

    /**
     * Add a new document to the collection with $properties
     * Returns the new $id
     *
     * @param array $properties
     * @return string
     */
    public function add(array $properties)
    {
        // adds a document
        $id                    = uniqid('');
        $properties['id']      = $id;
        $properties['created'] = time();
        $this->data[$id]       = $properties;
        $this->save($id);
        return $id;
    }

    /**
     * Delete a document
     * Returns boolean depending on success or failure
     *
     * @param string $id
     * @return bool
     */
    public function delete($id)
    {
        // find the document and delete it
        if ($this->first('id', $id)) {
            unset($this->data[$id]);
            $this->save($id);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Save the collection data to json
     *
     * @param string $id
     */
    protected function save($id)
    {
        $this->preSaveTasks($id);
        $jsondata = json_encode($this->data, JSON_PRETTY_PRINT);
        file_put_contents($this->dbfile, $jsondata);
    }

    /**
     * Perform tasks that need doing before saving collection data
     *
     * @param string $id
     */
    protected function preSaveTasks($id)
    {
        if ($this->persists) {
            $this->backup($id); // backup persistent models
        } else {
            $this->garbage(); // garbage collect non-persistent models
        }
    }

    /**
    * Clear out non-persistent collections/models
    * Deletes all documents that are at least one day old
    */
    protected function garbage()
    {
        // once a day = 86400
        $allDocuments = $this->all();
        $now          = time();
        foreach ($allDocuments as $document) {
            if ($now - $document['created'] > 86400) {
                $this->delete($document['id']);
            }
        }
    }

    /**
     * Make a backup diff of the changed document
     *
     * @param string $id
     */
    protected function backup($id)
    {
        if (!file_exists($this->backupfolder)) {
            mkdir($this->backupfolder);
        }
        $olddata = json_encode(json_decode(file_get_contents($this->dbfile), true)[$id], JSON_PRETTY_PRINT);
        $newdata = json_encode($this->data[$id], JSON_PRETTY_PRINT);
        //generate the diff
        $datestring = date("YmdHis");
        $differ     = new Differ;
        $result     = $differ->diff($olddata, $newdata);
        $backupfile = $this->backupfolder . '/' . $this->collectionname . '_' . $id . '_' . $datestring . '.diff';
        file_put_contents($backupfile, $result);
    }

    /**
     * Over-write the entire collection
     *
     * @param array $collection
     */
    protected function overwrite(array $collection)
    {
        //overwrites all data without validating - destructive!
        $this->data = $collection;
        $this->save();
    }

}
