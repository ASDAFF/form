<?if( !defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true ) die();?>

<div class="row">
	<div class="col-md-3">
	</div>
	<div class="col-md-6">
		<div class="form callback <?=$arParams["POPUP"] ? 'popup' : ''?>">
			<?if( $arResult["isFormNote"] == "Y" ){?>
				<div class="form-header">
					<i class="icon icon-check"></i>
					<div class="text">
						<div class="title"><?=GetMessage("SUCCESS_TITLE")?></div>
						<?=$arResult["FORM_NOTE"]?>
					</div>
				</div>
				<?if( $arParams["DISPLAY_CLOSE_BUTTON"] == "Y" ){?>
					<div class="form-footer" style="text-align: center;">
						<?=$arResult["CLOSE_BUTTON"]?>
					</div>
				<?}
			}else{?>
				<?=$arResult["FORM_HEADER"]?>
					<div class="form-header">
						<div class="text">
							<?if( $arResult["isIblockTitle"] ){?>
								<h1><?=$arResult["IBLOCK_TITLE"]?></h1>
							<?}?>
							<?if( $arResult["isIblockDescription"] ){
								if( $arResult["IBLOCK_DESCRIPTION_TYPE"] == "text" ){?>
									<p><?=$arResult["IBLOCK_DESCRIPTION"]?></p>
								<?}else{?>
									<?=$arResult["IBLOCK_DESCRIPTION"]?>
								<?}
							}?>
						</div>
					</div>
					<div class="form-body">
						<?$show_title = true;
						$tmp = '';?>
						<?foreach( $arResult["QUESTIONS"] as $FIELD_SID => $arQuestion ){
							if( $arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'hidden' ){
								echo $arQuestion["HTML_CODE"];
							}else{
								if( $tmp != $arQuestion["HINT"] ){
									$show_title = true;
								}else{
									$show_title = false;
								}?>
								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<?if( $show_title ){
												$tmp = $arQuestion["HINT"];?>
												<h2><?=$arQuestion["HINT"]?></h2>
											<?}?>
											<?=$arQuestion["CAPTION"]?>
											<div id="<?=$FIELD_SID?>" class="input <?=$arQuestion["FIELD_TYPE"]?>">
												<?=$arQuestion["HTML_CODE"]?>
											</div>
										</div>
									</div>
								</div>
							<?}
						}?>
						<?if( $arResult["isUseCaptcha"] == "Y" ){?>
							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<?=$arResult["CAPTCHA_CAPTION"]?>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
									<div class="form-group margin-bottom-30">
										<?=$arResult["CAPTCHA_IMAGE"]?>
										<span class="refresh"><span><?=GetMessage("REFRESH")?></span></span>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<div class="input <?=$arResult["CAPTCHA_ERROR"] == "Y" ? "error" : ""?>">
											<?=$arResult["CAPTCHA_FIELD"]?>
										</div>
									</div>
								</div>
							</div>
						<?}?>
					</div>
					<div class="form-footer">
						<div class="pull-right">
							<?=$arResult["SUBMIT_BUTTON"]?>
						</div>
					</div>
				<?=$arResult["FORM_FOOTER"]?>
			<?}?>
		</div>
	</div>
	<div class="col-md-3">
	</div>
</div>
<script>
	$(document).ready(function(){
		$('form[name="<?=$arResult["IBLOCK_CODE"]?>"]').validate({
			highlight: function( element ){
				$(element).parent().addClass('error');
			},
			unhighlight: function( element ){
				$(element).parent().removeClass('error');
			},
			submitHandler: function( form ){
				if( $('form[name="<?=$arResult["IBLOCK_CODE"]?>"]').valid() ){
					$(form).find('button[type="submit"]').attr("disabled", "disabled");
					form.submit();
				}
			},
			errorPlacement: function( error, element ){
				error.insertBefore(element);
			}
		});
		
		var base_mask = phone_mask.replace( /(\d)/g, '_' );
		
		$('form[name="<?=$arResult["IBLOCK_CODE"]?>"] input.phone').inputmask("mask", { "mask": phone_mask });
		$('form[name="<?=$arResult["IBLOCK_CODE"]?>"] input.phone').blur(function(){
			if( $(this).val() == base_mask || $(this).val() == '' ){
				if( $(this).hasClass('required') ){
					$(this).parent().find('label.error').html('Заполните это поле');
				}
			}
		});
	});
</script>