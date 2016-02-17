<?php

/* Главная страница сайта */
use frontend\models\News;
use frontend\models\Reports;

use yii\helpers\Html;
use yii\bootstrap\Carousel;
$this->title = 'TITLE';
Yii::$app->formatter->timeZone = 'UTC';
?>
<div class="site-index">
	<div class="top">		
        <?php $headPhotos = [
			Html::a(Html::img('/img/top9.png'),['news/37']),
			Html::a(Html::img('/img/top3.jpg'),['menu/biz']),
			Html::a(Html::img('/img/top2.jpg'),['banquets/']),
			Html::img('/img/top8.png'),
			Html::img('/img/top7.jpg'),
			Html::img('/img/top5.jpg'),
			Html::img('/img/top6.jpg'),
			Html::img('/img/top4.jpg'),
        ]; ?>
        <?= Carousel::widget(['items' => $headPhotos, 'clientOptions' => ['interval' => '5000']]); ?>		
	</div>
    <div class="blocks">
		<div class="item">
			<h2 class="title">Календарь событий</h2>
			<div class="item-content news">
				<?foreach(News::find()->where('position < 100')->orderBy(['position'=>SORT_ASC,'date'=>SORT_DESC])->limit(6)->all() as $news) :?>
					<div class="row">
						<div class="col-md-2 date">
						<?=( date('d.m.y') == Yii::$app->formatter->asDate($news->date, 'php:d.m.y') ? '<span class="today">сегодня</span>' : '' )?>
						<?=Yii::$app->formatter->asDate($news->date, 'dd MMMM')?> <span><?=Yii::$app->formatter->asDate($news->date, 'php:l')?><br/>
						<?=Yii::$app->formatter->asDate($news->date, 'HH:mm')?></span>
						</div>
						<div class="col-md-5">
							<?=Html::a($news->title, ['news/'.$news->id])?>
							<?=Html::tag('div', strip_tags($news->content), ['class'=>'crop'])?>
						</div>
						<div class="col-md-5"><?=($photo = $news->getPhotos()->one()) ? Html::a(Html::tag('div', Html::img($photo->path), ['class'=>'img']), ['news/'.$news->id]) : ''?></div>
					</div>
				<?endforeach?> 
			</div>			
		</div>
		<div class="item">
			<h1 class="title"><?=$this->title?></h1>
			<div class="item-content text">
				<?=Html::img('/img/main_cont_bg.png',['class'=> 'ramka'])?>
				<div class="gallery">
				<?php
				$photos = [
					Html::img('/img/top3/top3.jpg').'<div class="desc">Вход в РК</div>',
					Html::img('/img/top3/top1.jpg').'<div class="desc">Ресепшн РК</div>',
					Html::img('/img/top3/top2.jpg').'<div class="desc">Холл на первом этаже</div>',
					Html::img('/img/top3/top4.jpg').'<div class="desc">Каминный зал</div>',
					Html::img('/img/top3/top5.jpg').'<div class="desc">Пивной зал</div>',
					Html::img('/img/top3/top6.jpg').'<div class="desc">Ресторан Виноград</div>',
					Html::img('/img/top3/top7.jpg').'<div class="desc">Свадебный зал</div>',
				];
				?>
				<?= Carousel::widget(['items' => $photos, 'clientOptions' => ['interval' => '4000']]); ?>
				</div>
				<div class="content">
					<p>text</p>
				</div>
			</div>			
		</div>
		<div class="item">
			<h2 class="title">Фотоотчеты</h2>
			<div class="item-content photos">
				<div class="row">
				<?php foreach(Reports::find()->orderBy(['date'=>SORT_DESC])->limit(3)->all() as $reports) {
					echo '<div class="col-md-4">';
					echo Html::a($reports->title, ['reports/'.$reports->id], ['class' => 'link']);
					echo '<div class="imgs row">';
					foreach($reports->getPhotos()->limit(4)->all() as $photo):
							echo Html::tag('div', Html::a(Html::img($photo->path,['class'=> 'img-wrapper']), ['reports/'.$reports->id]),['class'=> 'col-md-6']); 
					endforeach; 
					echo '</div></div>';

				}?>
				</div>
				<div class="bottom">
					<?=Html::a('Все фотоотчеты', ['reports/index'])?>
				</div>
			</div>
		</div>       
    </div>
    

</div>
