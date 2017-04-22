<?php
interface IOwnable
{
    public function getOwners();
    public function isOwner(User $user);
    
}