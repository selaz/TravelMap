<?php

namespace Selaz\Entities\Map;

use SafeMySQL;
use Selaz\Tools\Config;
use Selaz\Tools\Database;

class PointCollection {
	
	protected $collection = [];
	private $where = '';
	private $db;

	public function __construct() {
		$this->db = Database::getInstance();
	}
	
	private function loadPoint( array $data ) {
		$p = new Point();
		
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
		$this->where .= $this->db->parse(
			" and ST_CONTAINS(ST_GEOMFROMTEXT(:cc),coords) ", 
			['cc'=>sprintf('POLYGON((%s))',implode(',', $points))]
		);
	}

	public function whereId(string $id) {
		$this->where .= $this->db->parse(" and p.message = ':mess' ", ['mess' => $id]);
	}

	public function whereType(?int $id) {
		$this->where .= $this->db->parse(" and p.type = :id ", ['id' => $id]);
	}
	
	public function load() {
		$sql = sprintf("select p.message as id,p.name,p.link,p.image,t.icon,t.color,
		st_x(coords) as lat,st_y(coords) as lon,p.description,t.name as type_name,p.type 
		from points p left join point_types t on p.type = t.id where 1 %s;", $this->where);

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