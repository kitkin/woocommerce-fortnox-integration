;( function( $ ) {
	$( function() {
		var templates = {
			notice: '<div id="fortnox-message" class="updated notice notice-{{ type }} is-dismissible"><p>{{{ message }}}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
		};
		
		/**
		 * Check connection
		 */
		if( $( '.button.fortnox-check-connection' ).length ) {
			$( '.button.fortnox-check-connection' ).on( 'click', function( event ) {
				event.preventDefault();
				
				var loader = $( this ).siblings( '.spinner' );
				
				loader.css( { visibility: "visible" } );
				
				$.ajax( {
					url: window.ajaxurl,
					data: {
						action: "check_" + $( this ).siblings( '[type=text]' ).attr( 'name' )
					},
					success: function( response ) {
						loader.css( { visibility: "hidden" } );
						
						alert( response.message );
					},
					dataType: "json"
				} );
			} );
		}
		
		/**
		 * Toggle advanced settings tab
		 */
		if( $( '[name=fortnox_show_advanced_settings]' ).length ) {
			$( '[name=fortnox_show_advanced_settings]' ).on( 'change', function() {
				$.ajax( {
					url: window.ajaxurl,
					data: {
						action: "fortnox_update_setting",
						settings: {
							fortnox_show_advanced_settings: $( this ).is( ':checked' ) ? 1 : 0
						}
					},
					success: function( response ) {
						console.log( response );
					},
					dataType: "json"
				} );
				
				if( $( this ).is( ':checked' ) )
					$( '.nav-tab-advanced' ).show();
				else
					$( '.nav-tab-advanced' ).hide();
			} );
		}
		
		/**
		 * AJAX bulk actions
		 */
		$( '.fortnox-bulk-action' ).on( 'click', function( event ) {
			event.preventDefault();
			
			var loader = $( this ).siblings( '.spinner' );
			
			if( ! $( this ).data( 'fortnox-bulk-action' ) )
				return console.warn( "No bulk action specified." );
			
			loader.css( { visibility: "visible" } );
			
			$.ajax( {
				url: window.ajaxurl,
				data: {
					action: "fortnox_bulk_action",
					bulk: $( this ).data( 'fortnox-bulk-action' )
				},
				success: function( response ) {
					loader.css( { visibility: "hidden" } );
					
					if( "undefined" !== typeof response.message )
						return alert( response.message );
				},
				dataType: "json"
			} );
		} );
		
		/**
		 * Sync order
		 */
		$( '.syncOrderToFortnox' ).on( 'click', function( event ) {
			event.preventDefault();
			
			var orderId = $( this ).data( 'order-id' );
			var nonce = $( this ).data( 'nonce' );
			var loader = $( this ).siblings( '.fortnox-spinner' );
			var status = $( this ).siblings( '.wetail-fortnox-status' );
			
			loader.css( { visibility: "visible" } );
			status.hide();
			
			$( '#fortnox-message' ).remove();
			
			$.ajax( {
				url: window.ajaxurl,
				data: {
					action: "fortnox_action",
					fortnox_action: "sync_order",
					order_id: orderId
				},
				type: "POST",
				dataType: "json",
				success: function( response ) {
					if( ! response.error )
						status.removeClass( 'wetail-icon-cross' ).addClass( 'wetail-icon-check' );
					
					loader.css( { visibility: "hidden" } );
					status.show();
					
					$( '#wpbody .wrap h1' ).after( Mustache.render( templates.notice, {
						type: response.error ? "error" : "success",
						message: response.message
					} ) );
					
					$( 'html, body' ).animate( { scrollTop: $( '#fortnox-message' ).offset().top - 100 } );
				}
			} );
			
			
			
			
			
			
			
			
			return;
			
			$.ajax( {
				url: ajaxurl,
				data: {
					action: 'sync_order',
					security: nonce,
					order_id: orderId
				},
				type: "post",
				success: function( response ) {
					
					console.log( response );
					
					loader.css( { visibility: "hidden" } );
					
					if( response.success ) {
						status.removeClass( 'pacsoft-icon-cross' ).addClass( 'pacsoft-icon-tick' );
						
						$( '#wpbody .wrap h1' ).after( Mustache.render( templates.notice, {
							type: "success",
							message: response.message
						} ) );
					}
					else {
						$( '#wpbody .wrap h1' ).after( Mustache.render( templates.notice, {
							type: "error",
							message: response.message
						} ) );
					}
					
					status.show();					
					
					$( 'html, body' ).animate( { scrollTop: $( '#fortnox-message' ).offset().top - 100 } );
				},
				dataType: "json"
			} );
		} );
		
		/**
		 * Sync product
		 */
		$( '.syncProductToFortnox' ).on( 'click', function( event ) {
			event.preventDefault();
			
			var productId = $( this ).data( 'product-id' );
			var nonce = $( this ).data( 'nonce' );
			var loader = $( this ).siblings( '.fortnox-spinner' );
			var status = $( this ).siblings( '.wetail-fortnox-status' );
			
			loader.css( { visibility: "visible" } );
			status.hide();
			
			$( '#fortnox-message' ).remove();
			
			$.ajax( {
				url: window.ajaxurl,
				data: {
					action: "fortnox_action",
					fortnox_action: "sync_product",
					product_id: productId
				},
				type: "POST",
				dataType: "json",
				success: function( response ) {
					if( ! response.error )
						status.removeClass( 'wetail-icon-cross' ).addClass( 'wetail-icon-check' );
					
					loader.css( { visibility: "hidden" } );
					status.show();
					
					$( '#wpbody .wrap h1' ).after( Mustache.render( templates.notice, {
						type: response.error ? "error" : "success",
						message: response.message
					} ) );
					
					$( 'html, body' ).animate( { scrollTop: $( '#fortnox-message' ).offset().top - 100 } );
				}
			} );
			
			
			
			
			
			
			return;
			
			
			
			event.preventDefault();
			
			var productId = $( this ).data( 'product-id' );
			var nonce = $( this ).data( 'nonce' );
			var loader = $( this ).siblings( '.fortnox-spinner' );
			var status = $( this ).siblings( '.wetail-fortnox-status' );
			
			loader.css( { visibility: "visible" } );
			status.hide();
			
			$( '#fortnox-message' ).remove();
			
			$.ajax( {
				url: ajaxurl,
				data: {
					action: 'sync_product',
					security: nonce,
					product_id: productId
				},
				type: "post",
				success: function( response ) {
					
					console.log( response );
					
					loader.css( { visibility: "hidden" } );
					
					if( response.success ) {
						status.removeClass( 'pacsoft-icon-cross' ).addClass( 'pacsoft-icon-tick' );
						
						$( '#wpbody .wrap h1' ).after( Mustache.render( templates.notice, {
							type: "success",
							message: response.message
						} ) );
					}
					else {
						$( '#wpbody .wrap h1' ).after( Mustache.render( templates.notice, {
							type: "error",
							message: response.message
						} ) );
					}
					
					status.show();					
					
					$( 'html, body' ).animate( { scrollTop: $( '#fortnox-message' ).offset().top - 100 } );
				},
				dataType: "json"
			} );
		} );
	} );
} )( jQuery );