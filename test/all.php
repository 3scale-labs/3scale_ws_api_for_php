<?php
require_once(dirname(__FILE__) . '/../lib/simpletest/autorun.php');

class AllTests extends TestSuite {
    function AllTests() {
        $this->TestSuite('All tests');

        $this->addFile('test/RemoteTest.php');
        $this->addFile('test/ThreeScaleClientTest.php');
    }
}
?>
