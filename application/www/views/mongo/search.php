<form action="/mongo/search" method="post">
    <input type="text" name="number" value="<?= $POST['number'] ?? '' ?>">
    <input type="submit" value="查询">
</form>
<pre>
<?php
print_r($data);
?>
</pre>
