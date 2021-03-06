<?php

namespace OP;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;

/**
 * Prints a JSON list of 10 files using GraphQL against the target server
 * 
 * @see RemoteAssetDownloadTask.php
 */
class RemoteAssetReadFilesController extends Controller
{

    private $statuscode;

    /**
     * Downloads a single file
     * @param SS_HTTPRequest $request checks the access code to make sure no
     * public spoofing
     */
    public function index(HTTPRequest $request)
    {
        $offset = $request->param('offset');
        $ignore = $request->getVar('ignore');
        // you can run php -S 127.0.0.1:8000 for an additional server to test locally
        // request 10 latest files
        $result = $this->DownloadFile(
            Controller::join_links($this->config()->target, 'admin/graphql'),
            json_encode($this->buildgraphql((int)$offset, $ignore))
        );
        // build up the JSON response
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody($result);
        $response->setStatusCode($this->statuscode);
        return $response;
    }

    /**
     * Creates the graphql to fetch the list of files from the remote server
     *
     * @returns array
     */
    public function buildgraphql($offset, $ignore)
    {

        $query = <<<GRAPHQL
query readPaginatedFiles(\$sortBy:ReadPaginatedFilesSortInputType, \$offset:Int, \$filter: PaginatedFileFilterInput) {
    readPaginatedFiles(
        limit:10,
        offset: \$offset,
        filter: \$filter,
        sortBy: [\$sortBy]
    ) {
        edges{
        node {
            __typename
            ... on File {
                id
                filename
                created
                published
                parentId
                }
            }
        }
        pageInfo {
            hasNextPage
            totalCount
        }
    }
}
GRAPHQL;
        return [
            "query" => "$query",
            "variables" => [
                "sortBy" => [
                    "field" => "ID",
                    "direction" => "DESC"
                ],
                "offset" => $offset,
                "filter" => ["filenameExcludeStarts" => $ignore]
            ]
        ];
    }


    /**
     * will download the file and return it
     * @param string $url
     * @return data
     */
    public function DownloadFile($url, $post_data, $timeout = 60)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: "Basic ' . base64_encode($this->config()->user . ':' . $this->config()->password) . '"',
            "Content-Type: application/json",
        ]);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        if (Director::isDev() || Director::isTest()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
        }

        $result = curl_exec($ch);
        $this->statuscode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return $result;
    }
}
