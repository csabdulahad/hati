<?php

namespace hati\filter;

/**
 * This enum class is used by {@link Filter::class}.
 *
 * @since 5.0.0
 * */

enum FilterOut {

	case EMPTY;

	case ILLEGAL;

	case INVALID;

	case NULL;

	case VAL_LEN_ERROR;

	case VAL_LEN_UNDER_ERROR;

	case VAL_LEN_OVER_ERROR;

	case RANGE_ERROR;

	case RANGE_UNDER_ERROR;

	case RANGE_OVER_ERROR;

	case RANGE_FRACTION_ERROR;

	case NOT_IN_OPTION;

	/*
	 * FilterOut cases returned by various filter methods
	 * matching any of the above should considered fail!
	 * */

	case OK;

	case CONTENT_TYPE_INVALID;

	case BAD_REQUEST_METHOD;

	case INVALID_REQUEST_DATA;
}