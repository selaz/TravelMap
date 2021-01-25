<?php

namespace Selaz\Entities\Map;

use SafeMySQL,
	Selaz\Tools\Config;

class Point {
	protected $coords;
	protected $name;
	protected $link;
	protected $image;
	protected $color;
	protected $icon;
	protected $description;

	protected $type;
	protected $id;
	protected $typeName;

	private $db;
	
	const updatable = [
		'name', 'coords', 'link', 'image', 'description', 'type'
	];

	public function __construct(?SafeMySQL $db = null) {
		if (empty($db)) {
			$c = Config::load('mysql.ini');
			$this->db = new SafeMySQL($c->get('travel',null));
		} else {
			$this->db = $db;
		}
	}
	
	public function getCoords(bool $reverse = false): array {
		if ($reverse) {
			return array_reverse($this->coords);
		} else {
			return $this->coords;
		}
	}
	
	public function getTypeName() {
		return $this->typeName;
	}

	public function setTypeName($typeName) {
		$this->typeName = $typeName;
	}
	
	public function getDesc(): ?string {
		return $this->description;
	}

	public function setDesc(?string $desc) {
		$this->description = $desc;
	}

	public function getName() {
		return $this->name;
	}

	public function getLinks() {
		return ($this->link) ? json_decode($this->link,true) : [];
	}

	public function getImage() {
		return $this->image;
	}

	public function getColor() {
		return $this->color;
	}

	public function getIcon() {
		return $this->icon;
	}

	public function setCoords(array $coords) {
		$this->coords = $coords;
	}

	public function setName($name) {
		$this->name = $name;
	}

	public function setLinks(?array $links) {
		$this->link = json_encode($links);
	}

	public function setImage($image) {
		$this->image = $image;
	}
	
	public function getType() {
		return $this->type ?? 2;
	}

	public function getId() {
		return $this->id;
	}

	public function setType($type) {
		$this->type = $type;
	}

	public function setId($id) {
		$this->id = $id;
	}

	public function setColor($color) {
		$this->color = $color;
	}

	public function setIcon($icon) {
		$this->icon = $icon;
	}

	public function save() {
		
		if (empty($this->getId())) {
			return false;
		}
		
		$result = $this->db->query(
			"insert into points (message,name,link,image,type,coords,created,description) "
			. "values (?s,?s,?s,?s,?i,point(?s,?s),now(),?s) on duplicate key update "
			. "name=?s,link=?s,image=?s,type=?i,description=?s;",
			$this->getId(),
			$this->getName(),
			json_encode($this->getLinks()),
			$this->getImage(),
			$this->getType(),
			floatval($this->coords[0]),
			floatval($this->coords[1]),
			$this->getDesc(),
			$this->getName(),
			json_encode($this->getLinks()),
			$this->getImage(),
			$this->getType(),
			$this->getDesc()
		);
		
		return ($result) ? $this->db->insertId() : false;
	}
	
	public function update() {
		if (empty($this->getId())) {
			return false;
		}
		
		$vars = get_object_vars($this);
		
		$update = array_filter(array_intersect_key($vars, array_flip(self::updatable)));
		
		$result = $this->db->query("update points set ?u where message = ?s;",$update, $this->getId());
		
		if ($result) {
			return $this->db->affectedRows();
		} else {
			return false;
		}
		
	}
	
	public function getBallonBody() {
		$text = '';
		
		if (!empty($this->getImage())) {
			$text .= sprintf('<img style="max-width:400px;" src="%s">', $this->getImage());
		}
		$text .= sprintf('<p>%s</p>', nl2br($this->getDesc()));
		
		if (!empty($this->getLinks())) {
			$text .= 'Ссылки:';
		}
		foreach ($this->getLinks() as $i => $link) {
			$text .= sprintf('&nbsp;<a href="%s" target="_blank">[%d]</a>', $link, $i);
		}
		
		return $text;
	}

}