<?php

interface DBInterface
{
    public function __construct();
    public function connect();
    public function query($query);
}