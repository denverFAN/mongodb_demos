<?php
// Composer autoloader for using mongoDB classes
require 'vendor/autoload.php';

// Make a Connection
$connection = new MongoDB\Client('mongodb://localhost:27017');
echo "Connection to database successfully <br/>";

// Select a Database
$db = $connection->testDB;
echo "Database testDB selected <br/>";

// Create a Collection
$collection = $db->users;
echo "Collection created succsessfully <br/>";

// Insert a Document
$document = [
    [
        "login" => "user_1",
        "email" => "a@a.ua",
        "firstname" => "Ivan",
        "lastname" => "Tsygan",
        "age" => [25, 10],
    ],
    [
        "login" => "user_2",
        "email" => "b@b.ua",
        "firstname" => "Ivan",
        "lastname" => "Tsygan",
        "age" => [45, 16],
    ],

];
$insertOneResult = $collection->insertMany($document);
echo "Inserted ".$insertOneResult->getInsertedCount()." document(s) <br/>";

// Find Many Documents
$cursor = $collection->find(['firstname' => 'Ivan', 'lastname' => 'Tsygan']);

// Query Projection (in SQL: SELECT firstname, lastname from users WHERE firstname = "Ivan")
$cursor = $collection->find(
    [
        'firstname' => 'Ivan',
    ],
    [
        'projection' => [
            // 0 - SELECT, 1 - do not SELECT
            '_id' => 0,
            'firstname' => 1,
            'lastname' => 1,
        ],
        'limit' => 2,
    ]
);

// Find with Regular Expressions
$cursor = $collection->find([
    'lastname' => new MongoDB\BSON\Regex('gan', 'i'),
]);

// Complex Queries with Aggregation
$cursor = $collection->aggregate([
    // deconstructs an array field from the input documents to output a document for each element
    ['$unwind' => '$age'],
    // create new attributes (_id, avgAge) for the output documents
    ['$group' => ['_id' => '$login', 'avgAge' => ['$avg' => '$age']]],
    // sort by avgAge in a reverse order
    ['$sort' => ['avgAge' => -1]],
    ['$limit' => 4],
]);

// empty current collection
$collection->drop();

// Update Many Documents
$collection->insertOne(['login' => 'user_1', 'money' => '80', 'firstname' => 'Ivan', 'lastname' => 'Tsygan']);
$collection->insertOne(['login' => 'user_2', 'money' => '125', 'firstname' => 'Ivan']);
$collection->insertOne(['login' => 'user_3', 'money' => '140', 'firstname' => 'Ivan']);
$updateResult = $collection->updateMany(
    ['firstname' => 'Ivan'],
    ['$set' => ['lastname' => 'Tsygan']],
    // When upsert is true and no documents match the specified filter, the operation creates a new document and inserts it.
    // If there are matching documents, then the operation modifies or replaces the matching document or documents.
    ['upsert' => true]
);
echo 'Matched '.$updateResult->getMatchedCount().' document(s)<br/>';
echo 'Modified '.$updateResult->getModifiedCount().' document(s)<br/>';

// Replace Documents
$updateResult = $collection->replaceOne(
    ['login' => 'user_1'],
    ['firstname' => 'Ivan', 'lastname' => 'Tsygan']
);

// Delete Many Documents
$deleteResult = $collection->deleteMany(['login' => 'user_3']);
echo 'Deleted '.$deleteResult->getDeletedCount().' document(s)<br/>';

/*Collations are not supported by the server
// Find One And Delete, Assign a Collation
$document = $collection->findOneAndDelete(
    // checks if money has a numeric value greater than 130
    ['money' => ['$gt' => '130']],
    [
        'collation' => [
            // Set the numericOrdering collation parameter to true to compare numeric strings by their numeric values.
            'numericOrdering' => true,
        ],
    ]
);*/

/*// Get all users (that have access) from the system.users collection in the ADMIN database
$users = $connection->selectCollection('admin', 'system.users')->find()->toArray();*/

// Execute Database Commands with Custom Read Preference
$cursor = $db->command(
    [
        'dropUser' => 'superAdmin',
    ]
);
$cursor = $db->command(
    // Create user that will have access to current db
    [
        'createUser' => 'superAdmin',
        'pwd' => '000000',
        'roles' => ['readWrite'],
    ],
    [
        'readPreference' => new MongoDB\Driver\ReadPreference(MongoDB\Driver\ReadPreference::RP_PRIMARY),
    ]
);


// Execute Database Commands and Iterate Results from a Cursor
$cursor = $db->command(['listCollections' => 1]);

// GridFS uses two collections: to store file chunks (fs.chunks), to store file metadata (fs.files)
// selectGridFSBucket(prefix for the metadata and chunk collections(default-'fs'), the size of each chunk in bytes(default-'261120'), defaults for read and write operations)
// Uploading Files with Writable Streams (upload the entire contents of a readable stream in one call)
$bucket = $db->selectGridFSBucket();
$file = fopen('oldImage.jpg', 'rb');
$bucket->uploadFromStream('oldImage.jpg', $file);

// Get the _id for an existing readable or writable GridFS stream
$bucket = $db->selectGridFSBucket();
$stream = $bucket->openDownloadStreamByName('oldImage.jpg');
$fileId = $bucket->getFileIdForStream($stream);

// Finding File Metadata
$bucket = $db->selectGridFSBucket();
// Get all files by filename
$cursor = $bucket->find(['filename' => 'oldImage.jpg']);
// Get exact file by _id
$stream = $bucket->openDownloadStream($fileId);
$metadata = $bucket->getFileDocumentForStream($stream);

// Downloading Files with Readable Streams (download the file all at once and write it to a writable stream)
$bucket = $db->selectGridFSBucket();
$file = fopen('newImage.jpg', 'wb');
// $fileId denotes the _id of an existing file in GridFS
$bucket->downloadToStream($fileId, $file);

// Selecting Files by Filename and Revision
$bucket = $db->selectGridFSBucket();
$file = fopen('newImage.jpg', 'wb');
// revision: -1-the most recent revision, 0 - the original stored file, 1 - the first revision etc.
$bucket->downloadToStreamByName('oldImage.jpg', $file, ['revision' => 0]);

// Deleting File by its _id
$bucket = $db->selectGridFSBucket();
$bucket->delete($fileId);

// Create Indexes (index order: 1 - ascending index, -1 - descending index)
$result = $collection->createIndex(['login' => 1]);

// List Indexes
foreach ($collection->listIndexes() as $indexInfo) {
    echo "<pre>";
    var_dump($indexInfo);
    echo "</pre>";
}

// Drop Indexes
$result = $collection->dropIndex('login_1');

// Importing the dataset into MongoDB (from json file)
$filename = 'colors.json';
$lines = file($filename, FILE_IGNORE_NEW_LINES);
$bulk = new MongoDB\Driver\BulkWrite;
foreach ($lines as $line) {
    $bson = MongoDB\BSON\fromJSON($line);
    $document = MongoDB\BSON\toPHP($bson);
    $bulk->insert($document);
}
$manager = new MongoDB\Driver\Manager();
$result = $manager->executeBulkWrite('testDB.colors', $bulk);
echo 'Inserted '.$result->getInsertedCount().' document(s)<br/>';










