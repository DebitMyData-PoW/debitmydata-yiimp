<?php

class db_hashuser extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'hashuser';
	}

	public function rules()
	{
		return array(
		);
	}

	public function relations()
	{
		return array(
//        		'db_accounts' => array(self::BELONGS_TO, 'db_accounts', 'userid'),
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}
}

