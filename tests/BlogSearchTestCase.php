<?php

namespace Tests\Blog;

use Tests\TestCase;

/**
 * BlogSearchTestCase
 * 
 * @uses TestCase
 * PHP version 7
 * 
 * @category  
 * @package   
 * @author    Qinjianbo <279250819@qq.com> 
 * @copyright 2016-2019 boboidea Co. All Rights Reserved.
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @version   GIT:<git_id>
 * @link      https://www.boboidea.com
 */
class BlogSearchTestCase extends TestCase
{
    /**
     * testCreate 
     * 
     * 
     * @access public
     * 
     * @return mixed
     */
    public function testSearch()
    {
        $response = $this->get('/api/search/v1/blogs?q=mysql&p=1&ps=10');

        $response->assertStatus(200);
        //$response->assertJsonStructure(['data', 'code', 'msg']);
        //$response->assertJson(['code' => 0]);
    }
}
