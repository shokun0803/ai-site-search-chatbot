( function () {
	function parseMetrics( container ) {
		if ( ! container ) {
			return null;
		}

		var raw = container.getAttribute( 'data-metrics' );

		if ( ! raw ) {
			return null;
		}

		try {
			return JSON.parse( raw );
		} catch ( error ) {
			return null;
		}
	}

	function drawUsageChart( canvas, series ) {
		if ( ! canvas || ! series.length ) {
			return;
		}

		var context = canvas.getContext( '2d' );

		if ( ! context ) {
			return;
		}

		var parentWidth = canvas.parentElement ? canvas.parentElement.clientWidth : canvas.clientWidth;
		var width = Math.max( 320, parentWidth || 640 );
		var height = 180;
		var padding = { top: 16, right: 16, bottom: 28, left: 48 };
		var innerWidth = width - padding.left - padding.right;
		var innerHeight = height - padding.top - padding.bottom;
		var values = series.map( function ( item ) {
			return Math.max( 0, Number( item.total_tokens ) || 0 );
		} );
		var maxValue = Math.max.apply( null, values.concat( [ 1 ] ) );

		canvas.width = width;
		canvas.height = height;
		context.clearRect( 0, 0, width, height );
		context.font = '12px sans-serif';
		context.lineWidth = 1;
		context.strokeStyle = '#d0d5dd';
		context.fillStyle = '#667085';

		for ( var i = 0; i < 4; i += 1 ) {
			var gridY = padding.top + ( innerHeight * i / 3 );
			var gridValue = Math.round( maxValue - ( maxValue * i / 3 ) );
			context.beginPath();
			context.moveTo( padding.left, gridY );
			context.lineTo( width - padding.right, gridY );
			context.stroke();
			context.fillText( String( gridValue ), 8, gridY + 4 );
		}

		context.strokeStyle = '#2271b1';
		context.lineWidth = 2;
		context.beginPath();

		values.forEach( function ( value, index ) {
			var x = padding.left + ( innerWidth * index / Math.max( 1, values.length - 1 ) );
			var y = padding.top + innerHeight - ( innerHeight * value / maxValue );

			if ( 0 === index ) {
				context.moveTo( x, y );
			} else {
				context.lineTo( x, y );
			}
		} );

		context.stroke();
		context.fillStyle = 'rgba(34, 113, 177, 0.12)';
		context.lineTo( width - padding.right, height - padding.bottom );
		context.lineTo( padding.left, height - padding.bottom );
		context.closePath();
		context.fill();

		context.fillStyle = '#2271b1';
		values.forEach( function ( value, index ) {
			var x = padding.left + ( innerWidth * index / Math.max( 1, values.length - 1 ) );
			var y = padding.top + innerHeight - ( innerHeight * value / maxValue );
			context.beginPath();
			context.arc( x, y, 3, 0, Math.PI * 2 );
			context.fill();
		} );

		context.fillStyle = '#667085';
		var labelStep = Math.max( 1, Math.ceil( values.length / 6 ) );
		series.forEach( function ( item, index ) {
			if ( index % labelStep !== 0 && index !== values.length - 1 ) {
				return;
			}

			var x = padding.left + ( innerWidth * index / Math.max( 1, values.length - 1 ) );
			var label = String( item.local_day_key || '' ).slice( 5 );
			context.save();
			context.translate( x, height - 8 );
			context.rotate( -0.35 );
			context.fillText( label, 0, 0 );
			context.restore();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var panel = document.querySelector( '.aiscb-usage-panel' );
		var metrics = parseMetrics( panel );

		if ( ! metrics || ! Array.isArray( metrics.daily ) ) {
			return;
		}

		var canvas = panel.querySelector( '.aiscb-usage-chart' );
		drawUsageChart( canvas, metrics.daily );

		window.addEventListener( 'resize', function () {
			drawUsageChart( canvas, metrics.daily );
		} );
	} );
}() );
