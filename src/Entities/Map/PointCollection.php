<?php

namespace Selaz\Entities\Map;

use SafeMySQL;
use Selaz\Tools\Config;

class PointCollection {
	
	protected $collection = [];
	private $where = '';
	private $db;

	public function __construct() {
		$c = Config::load('main.ini');
		$this->db = new SafeMySQL($c->getBlock('mysql'));
	}
	
	private function loadPoint( array $data ) {
		$p = new Point($this->db);
		
		$p->setColor($data['color']);
		$p->setCoords([floatval($data['lat']), floatval($data['lon'])]);
		$p->setIcon($data['icon']);
		$p->setImage($data['image']);
		$p->setLinks(json_decode($data['link'],true));
		$p->setName($data['name']);
		$p->setDesc($data['description']);
		$p->setId($data['id']);
		$p->setTypeName($data['type_name']);
		$p->setType($data['type']);
		
		$this->collection[] = $p;
	}
	
	public function wherePolygon(array $points) {
		$this->where .= sprintf(" and ST_CONTAINS(ST_GEOMFROMTEXT('POLYGON((%s))'),coords) ", implode(',', $points));
	}

	public function whereId(string $id) {
		$this->where .= sprintf(" and p.message = '%s' ", $id);
	}

	public function whereType(?int $id) {
		$this->where .= sprintf(" and p.type = %d ", $id);
	}
	
	public function load() {
		$sql = $this->db->parse("select p.message as id,p.name,p.link,p.image,t.icon,t.color,st_x(coords) as lat,st_y(coords) as lon,p.description,t.name as type_name,p.type "
			. "from points p left join point_types t on p.type = t.id where 1 ?p;",
			$this->where
		);

		$data = $this->db->getAll($sql);
		
		if (is_array($data)) {
			foreach ( $data as $row ) {
				$this->loadPoint($row);
			}
		} 

		return $sql;
	}
	
	/**
	 * 
	 * @return \Selaz\Entities\Map\Point[]
	 */
	public function getCollection() {
		return $this->collection;
	}
	
	/**
	 * 
	 * @return \Selaz\Entities\Map\Point
	 */
	public function getNext() {
		$return = current($this->collection);
		next($this->collection);
		
		return $return;
	}
}