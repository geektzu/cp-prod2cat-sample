<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Plugin_Name
 *
 * @wordpress-plugin
 * Plugin Name:       Sample - Composite Products - Products to Category Scenario Extention
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Your Name or Your Company
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       plugin-name
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PLUGIN_NAME_VERSION', '1.0.0' );

add_action('admin_menu', 'test_plugin_setup_menu');
 
function test_plugin_setup_menu(){
	add_menu_page( 'Test Plugin Page', 'CP Prod2Cat Converter', 'manage_options', 'test-plugin', 'test_init' );
}

require_once(ABSPATH . 'wp-config.php');

function get_composite_products() {
	$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD,"wordpress");
	// Check connection
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
    
 	$sql = 'SELECT * FROM wp_posts p INNER JOIN wp_postmeta m ON m.post_id=p.ID WHERE m.meta_key="_bto_data"';
 	echo "<option value=''>Please select a composite product ...</option>";
	if($result = mysqli_query($conn, $sql)){
		if(mysqli_num_rows($result) > 0){
		while($row = mysqli_fetch_array($result)){
			$string = $row["post_title"];
			echo "<option value='".$row["ID"]."'>".$string."</option>";
		}
		// Free result set
		mysqli_free_result($result);
		} else{
		echo "No records matching your query were found.".PHP_EOL;
		}
	} else{
		echo "ERROR: Could not able to execute $sql. " . mysqli_error($conn).PHP_EOL;
	}

    mysqli_close($conn);
}

function test_init(){
	echo "<h1>Composite Products - Product2Category Converter</h1>";
	echo "Composite Product ID:";
	echo "<select name='product_selector' id='cpprodid'>";
	get_composite_products();
	echo "</select>";
	echo "<br>";
	echo "<button onclick='javascript:window.location.href = \"admin.php?page=test-plugin&cpprodid=\" + escape(document.getElementById(\"cpprodid\").value);'>Convert!</button>";
	$prod_id = htmlspecialchars($_GET["cpprodid"]) ;
	if (is_numeric($prod_id) && $prod_id) { //simple anti sql injection mechanisim to ensure the query string is a number not some maliocious sql statement
		echo "<br><br>Debug info:<br>";
		echo "<textarea style='width:100%;height:500px'>";
		prod2cat($prod_id);
		echo "</textarea>";
		echo "<script>alert('Converted!')</script>";
		echo "<br><br><h1>You could go back to the Products page and check on the scenario configuration for Composite Product id=".$prod_id."</h1>";
	}
	
}

function prod2cat($product_id) {
	$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD,"wordpress");

	// Check connection
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	echo 'Connected successfully'.PHP_EOL;

	// product id of the composite product such as a gaming PC below:

	function get_category_id($product_id,$conn) {
		$sql = 'SELECT wt.* FROM wp_posts p INNER JOIN wp_term_relationships r ON r.object_id=p.ID INNER JOIN wp_term_taxonomy t ON t.term_taxonomy_id = r.term_taxonomy_id INNER JOIN wp_terms wt on wt.term_id = t.term_id WHERE p.ID='.$product_id.' AND t.taxonomy="product_cat" LIMIT 1;';

		if($result = mysqli_query($conn, $sql)){
			if(mysqli_num_rows($result) > 0){
			while($row = mysqli_fetch_array($result)){
				$string = $row["term_id"];
				return $string;
			}
			// Free result set
			mysqli_free_result($result);
			} else{
			echo "No records matching your query were found.".PHP_EOL;
			}
		} else{
			echo "ERROR: Could not able to execute $sql. " . mysqli_error($conn).PHP_EOL;
		}
	}

	function get_products_by_category_id($cat_id,$conn) {
		$sql = 'SELECT ID, post_title from wp_posts as posts LEFT JOIN wp_term_relationships terms on posts.ID = terms.object_id WHERE terms.term_taxonomy_id = '.$cat_id;

		$product_ids = array();

		if($result = mysqli_query($conn, $sql)){
			if(mysqli_num_rows($result) > 0){
			while($row = mysqli_fetch_array($result)){
				$string = $row["ID"];
				$product_ids[] = (int)$string;
			}
			// Free result set
			mysqli_free_result($result);
			} else{
			echo "No records matching your query were found.".PHP_EOL;
			}
		} else{
			echo "ERROR: Could not able to execute $sql. " . mysqli_error($conn).PHP_EOL;
		}

		return $product_ids;
	}
	

	function update_scenario($product_id,$conn,$value) {
		$sql = "update wp_postmeta set meta_value = '".serialize($value)."' where meta_key = '_bto_scenario_data' and post_id = '".$product_id."';";
		echo $sql.PHP_EOL;
		if ($conn->query($sql) === TRUE) {
		echo "Record updated successfully";
		} else {
		echo "Error updating record: " . $conn->error;
		}
	}


	$sql = 'select * from wp_postmeta where meta_key = "_bto_scenario_data" and post_id = "'.$product_id.'";';


	$unserialized = array();


	if($result = mysqli_query($conn, $sql)){
		if(mysqli_num_rows($result) > 0){
			while($row = mysqli_fetch_array($result)){
				$string = $row["meta_value"];
				$unserialized=unserialize($string);
				
				break; //as each product will only has one meta key/value for scenarios
			}
			// Free result set
			mysqli_free_result($result);
		} else{
			echo "No records matching your query were found.".PHP_EOL;
		}
	} else{
		echo "ERROR: Could not able to execute $sql. " . mysqli_error($conn).PHP_EOL;
	}

	echo(json_encode($unserialized).PHP_EOL);

	$new_scenarios = array(); // reconstruct an array object

	foreach ($unserialized as $key => $value) {
		// $arr[3] will be updated with each value from $arr...
		echo "Extending Scenario to all products under the same category: {$key} => {$value['title']} ".PHP_EOL;
		echo "Condition:".json_encode($value['component_data']).PHP_EOL;
		$new_value = $value;
		foreach ($value['component_data'] as $key1 => $value1) {
			if (!empty($value1) && $value1[0]>0) {
				echo json_encode($value1[0]).PHP_EOL;
				$category_id = get_category_id($value1[0],$conn);
				echo "category id = ".$category_id.PHP_EOL;
				$product_ids = get_products_by_category_id($category_id,$conn);
				echo "product ids = ".json_encode($product_ids).PHP_EOL;
				$new_value['component_data'][$key1] = $product_ids;
			}
		}
		echo "Actions:".json_encode($value['scenario_actions']['conditional_options']['component_data']).PHP_EOL;
		foreach ($value['scenario_actions']['conditional_options']['component_data'] as $key2 => $value2) {
			if (!empty($value2) && $value2[0]>0) {
				echo json_encode($value2[0]).PHP_EOL;
				$category_id = get_category_id($value2[0],$conn);
				echo "category id = ".$category_id.PHP_EOL;
				$product_ids = get_products_by_category_id($category_id,$conn);
				echo "product ids = ".json_encode($product_ids).PHP_EOL;
				$new_value['scenario_actions']['conditional_options']['component_data'][$key2] = $product_ids;
			}
		}
		$new_scenarios[$key] = $new_value;

	}

	echo(json_encode($new_scenarios).PHP_EOL);

	echo(serialize($new_scenarios).PHP_EOL);

	update_scenario($product_id,$conn,$new_scenarios);

	mysqli_close($conn);

}