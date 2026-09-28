<?php
/**
 * Email layout. Themes can override it at aula-virtual/emails/layout.php.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string $body Rendered, sanitised HTML body.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_color = get_option( 'av_brand_color', '#1d4ed8' );
$av_color = is_string( $av_color ) && preg_match( '/^#[0-9a-f]{6}$/i', $av_color ) ? $av_color : '#1d4ed8';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;">
					<tr>
						<td style="background:<?php echo esc_attr( $av_color ); ?>;padding:20px 28px;color:#ffffff;font-size:18px;font-weight:bold;">
							<?php echo esc_html( wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:28px;font-size:15px;line-height:1.6;">
							<?php echo wp_kses_post( $body ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:16px 28px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;">
							<?php echo esc_html( wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) ); ?>
							&middot; <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#6b7280;"><?php echo esc_html( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ); ?></a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
