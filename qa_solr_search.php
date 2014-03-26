<?php

require_once('vendor/autoload.php');

class qa_solr_search {
    var $directory, $urltoroot;

    function load_module($directory, $urltoroot)
    {
        $this->directory=$directory;
        $this->urltoroot=$urltoroot;
    }


    function admin_form()
    {
        $saved=false;

        if (qa_clicked('solr_save_button')) {
            qa_opt('solr_endpoint', qa_post_text('solr_endpoint_field'));

            $saved=true;
        }

        $form=array(
            'ok' => $saved ? 'Solr settings saved' : null,

            'fields' => array(
                'endpoint' => array(
                    'label' => 'Solr Endpoint URL:',
                    'value' => qa_opt('solr_endpoint'),
                    'tags' => 'NAME="solr_endpoint_field"',
                ),

            ),

            'buttons' => array(
                array(
                    'label' => 'Save Changes',
                    'tags' => 'NAME="solr_save_button"',
                ),
            ),
        );

        return $form;
    }

    private function get_solr_client() {
        $endpoint = parse_url(qa_opt('solr_endpoint'));
        $solr_config = array(
            'endpoint' => array(
                'main' => array(
                    'host' => $endpoint['host'],
                    'port' => $endpoint['port'],
                    'path' => $endpoint['path'],
                )
            )
        );

        $client = new Solarium\Client($solr_config);
        $adapter = $client->getAdapter();
        $adapter->setOptions(array('CURLOPT_ENCODING'=>'UTF-8'));

        return $client;
    }

    private function purge_html($str) {
        // Strip HTML Tags
        $clear = strip_tags($str);

        // Clean up things like &amp;
        $clear = html_entity_decode($clear);

        // Strip out any url-encoded stuff
        $clear = urldecode($clear);

        // Replace non-AlNum characters with space
        //$clear = preg_replace('/[^A-Za-z0-9]/', ' ', $clear);

        // Replace Multiple spaces with single space
        $clear = preg_replace('/ +/', ' ', $clear);

        // Replace newlines with single space
        $clear = preg_replace('/[\n\r]+/', ' ', $clear);

        // Trim the string of leading/trailing space
        $clear = trim($clear);

        //return utf8_encode($clear);
        return $clear;
    }

    private function get_answers($questionid) {
        $result = qa_db_query_raw("SELECT * FROM qa_posts WHERE type='A' AND parentid='$questionid'");
        $answers = qa_db_read_all_assoc($result);
        return $answers;
    }

    private function get_comments($questionid) {
        $result = qa_db_query_raw("SELECT * FROM qa_posts WHERE type='C' AND parentid='$questionid'");
        $comments = qa_db_read_all_assoc($result);
        return $comments;
    }

    private function index_question($questionid) {
        $result = qa_db_query_raw('SELECT * FROM qa_posts WHERE postid=' . $questionid);
        $question = qa_db_read_one_assoc($result);
        $title = $this->purge_html($question['title']);
        $content = $this->purge_html($question['content']);

        $client = $this->get_solr_client();
        $update = $client->createUpdate();

        $doc = $update->createDocument();
        $doc->id = "qa_$questionid";
        $doc->type = "question_answer";
        $doc->question_id = $questionid;
        $doc->url = qa_q_path($questionid, $title, true);
        $doc->title = $title;
        $doc->question = $content;
        $answers = array();
        $comments = array();

        if ($question['updated'])
            $doc->lastupdate = $update->getHelper()->formatDate($question['updated']);
        else
            $doc->lastupdate = $update->getHelper()->formatDate($question['created']);

        foreach ($this->get_comments($questionid) as $c) {
            $comments[] = $c['content'];
        }

        foreach ($this->get_answers($questionid) as $a) {
            $answers[] = $this->purge_html($a['content']);
            foreach ($this->get_comments($a['postid']) as $c) {
                $comments[] = $c['content'];
            }
        }

        if ( count($answers) > 0 ) $doc->answers = $answers;
        if ( count($comments) > 0 ) $doc->comments = $comments;

        $doc->n_answers = count($answers);

        $update->addDocument($doc);
        $update->addCommit();
        $result = $client->update($update);

    }

    function index_post($postid, $type, $questionid, $parentid, $title, $content, $format, $text, $tagstring, $categoryid) {
        $this->index_question($questionid);
    }


    function unindex_post($postid) {

    }

    function process_search($userquery, $start, $count, $userid, $absoluteurls, $fullcontent) {
        $results = array();

        // get the client instance
        $client = $this->get_solr_client();

        // get a select query instance
        $query = $client->createSelect();

        // get the dismax component and set a boost query
        $edismax = $query->getEDisMax();
        $edismax->setQueryFields('title^5 question^2 answers^2 comments^1');

        // this query is now a dismax query
        $query->setQuery($userquery);

        // this executes the query and returns the result
        $resultset = $client->select($query);

        // show documents using the resultset iterator
        foreach ($resultset as $document) {
            $item = array(
                'question_postid' => $document['question_id'],
                //'match_postid' => $document['question_id'],
                //'page_pageid' => $document['question_id'],
                'title' => $document['title'],
                //'url' => $document['url'],
            );
            $results[] = $item;
        }

        return $results;
    }


}