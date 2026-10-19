/**
 * VK Link Target Controller - Block Editor Panel
 * ブロックエディタ用サイドバーパネル
 *
 * Replaces the legacy add_meta_box() with PluginDocumentSettingPanel
 * so that WordPress 7.0 RTC (Real-Time Collaboration) is not blocked.
 * レガシーなadd_meta_box()をPluginDocumentSettingPanelに置き換え、
 * WordPress 7.0 RTC（リアルタイム共同編集）がブロックされないようにする。
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	TextControl,
	CheckboxControl,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { useEffect, useCallback } from '@wordpress/element';

// ID of the URL field in the panel. wpLink.open() receives this same ID and
// keeps the element as "the field being edited", so use this one constant
// for both the TextControl and the open() call.
// パネルのURL欄のID。wpLink.open() に同じIDを渡し、wpLink はその要素を
// 「編集中の入力欄」として保持するため、TextControl と open() の両方で
// この定数を使うこと。
const LINK_FIELD_ID = 'vk-ltc-react-link';

const VkLtcPanel = () => {
	const { postType, candidatePostTypes } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postType: editor.getCurrentPostType(),
			candidatePostTypes: window.vkLtcEditor?.postTypes || [],
		};
	}, [] );

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	// Only show panel for enabled post types.
	// 有効な投稿タイプでのみパネルを表示
	if ( ! candidatePostTypes.includes( postType ) ) {
		return null;
	}

	const link = meta?.[ 'vk-ltc-link' ] ?? '';
	const target = meta?.[ 'vk-ltc-target' ];
	const isTargetBlank = target === '1' || target === 1;

	/**
	 * Update a single meta key value.
	 * メタキーの値を更新する
	 *
	 * @param {string} key   Meta key name.
	 * @param {*}      value Meta value.
	 */
	const updateMeta = useCallback(
		( key, value ) => {
			setMeta( { ...meta, [ key ]: value } );
		},
		[ meta, setMeta ]
	);

	/**
	 * Open the WordPress internal link search dialog (wpLink).
	 * WordPress内部リンク検索ダイアログ（wpLink）を開く
	 */
	const openLinkDialog = useCallback( () => {
		if ( typeof window.wpLink === 'undefined' ) {
			return;
		}
		window.vkLtcLinkMode = true;
		window.wpLink.open( LINK_FIELD_ID, link, '' );
	}, [ link ] );

	// Intercept wp-link-submit click when opened from this panel.
	// このパネルから開いた場合のwp-link-submitクリックをインターセプトする
	useEffect( () => {
		const handleSubmit = ( e ) => {
			if ( ! window.vkLtcLinkMode ) {
				return;
			}
			if ( ! e.target || e.target.id !== 'wp-link-submit' ) {
				return;
			}
			if ( typeof window.wpLink === 'undefined' ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();

			const attrs = window.wpLink.getAttrs();
			if ( attrs.href ) {
				updateMeta( 'vk-ltc-link', attrs.href );
			}

			window.wpLink.textarea = document.body;
			window.wpLink.close( 'noReset' );
			window.vkLtcLinkMode = false;
		};

		const handleClose = () => {
			window.vkLtcLinkMode = false;
		};

		// Use capture phase to run before wpLink's own handler.
		// wpLinkのハンドラより先に実行するためキャプチャフェーズを使用
		document.addEventListener( 'click', handleSubmit, true );
		jQuery( document ).on( 'wplink-close.vkltc', handleClose );

		return () => {
			document.removeEventListener( 'click', handleSubmit, true );
			jQuery( document ).off( 'wplink-close.vkltc' );
		};
	}, [ updateMeta ] );

	/**
	 * Open the WordPress media uploader to select a file.
	 * WordPressメディアアップローダーを開いてファイルを選択する
	 */
	const openMediaUploader = () => {
		const uploader = wp.media( {
			title: __( 'Choose File', 'vk-link-target-controller' ),
			button: {
				text: __( 'Choose File', 'vk-link-target-controller' ),
			},
			multiple: false,
		} );
		uploader.on( 'select', () => {
			const file = uploader
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			updateMeta( 'vk-ltc-link', file.url );
		} );
		uploader.open();
	};

	return (
		<PluginDocumentSettingPanel
			name="vk-ltc-panel"
			title={ __( 'URL to redirect to', 'vk-link-target-controller' ) }
			className="vk-ltc-panel"
		>
			<p style={ { fontSize: '12px', color: '#757575' } }>
				{ __(
					'If you enter an URL here your visitors will access that URL directly when they click on the title of this post in Recent Posts list.',
					'vk-link-target-controller'
				) }
			</p>

			<TextControl
				id={ LINK_FIELD_ID }
				label={ __( 'URL', 'vk-link-target-controller' ) }
				value={ link }
				onChange={ ( value ) => updateMeta( 'vk-ltc-link', value ) }
				placeholder="https://"
				__nextHasNoMarginBottom
			/>

			<div
				style={ {
					display: 'flex',
					gap: '8px',
					flexWrap: 'wrap',
					marginTop: '8px',
				} }
			>
				<Button variant="secondary" onClick={ openLinkDialog }>
					{ __(
						'Search internal link',
						'vk-link-target-controller'
					) }
				</Button>
				<Button variant="secondary" onClick={ openMediaUploader }>
					{ __( 'File Link', 'vk-link-target-controller' ) }
				</Button>
			</div>

			<div style={ { marginTop: '16px' } }>
				<CheckboxControl
					label={ __(
						'Open the link in a separate window',
						'vk-link-target-controller'
					) }
					checked={ isTargetBlank }
					onChange={ ( checked ) =>
						updateMeta( 'vk-ltc-target', checked ? '1' : '0' )
					}
				/>
			</div>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'vk-ltc-panel', {
	render: VkLtcPanel,
} );
